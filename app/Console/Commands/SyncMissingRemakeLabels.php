<?php

namespace App\Console\Commands;

use App\Models\Sprint;
use App\Models\SprintRemake;
use App\Domains\Estimation\EstimatePointsResolver;
use App\Services\Trello\TrelloClient;
use App\Services\Trello\TrelloSprintBoardReader;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

class SyncMissingRemakeLabels extends Command
{
    protected $signature = 'trello:remake-labels:sync-missing {--sprint=} {--limit=}';

    protected $description = 'Syncs remake labels and estimate points from Trello for existing sprint remakes.';

    public function handle(
        TrelloClient $trello,
        TrelloSprintBoardReader $reader,
        EstimatePointsResolver $pointsResolver,
    ): int
    {
        $sprintId = $this->option('sprint');
        $limit = (int) ($this->option('limit') ?? 0);

        $query = SprintRemake::query()
            ->whereNull('removed_at')
            ->whereNotNull('trello_card_id');

        if ($sprintId) {
            $query->where('sprint_id', (int) $sprintId);
        }

        if ($limit > 0) {
            $query->limit($limit);
        }

        $remakes = $query->orderBy('id')->get();
        if ($remakes->isEmpty()) {
            $this->info('No remakes to sync.');
            return Command::SUCCESS;
        }

        $removeMap = $this->normalizeLabelPoints(config('trello_sync.remake_label_actions.remove', []));

        $now = Carbon::now();
        $updated = 0;

        $cardLookup = $this->fetchCardsBatch($trello, $remakes->pluck('trello_card_id')->filter()->all());

        $customFieldCache = [];

        foreach ($remakes as $remake) {
            if (!$remake->trello_card_id) {
                $this->warn("Remake {$remake->id} missing Trello card id; skipping.");
                continue;
            }

            $card = $cardLookup[$remake->trello_card_id] ?? null;
            if (!$card) {
                continue;
            }

            $updates = [];

            $boardId = $remake->sprint?->trello_board_id;
            $remakeLabel = null;
            if ($boardId) {
                if (!array_key_exists($boardId, $customFieldCache)) {
                    $customFields = $reader->fetchCustomFields($boardId);
                    $customFieldCache[$boardId] = [
                        'lookup' => $reader->buildDropdownLookup($customFields),
                        'estimationField' => $reader->findCustomFieldIdByName($customFields, 'Estimation'),
                        'remakeLabelField' => $this->resolveRemakeLabelFieldId($reader, $customFields),
                    ];
                }
                $lookup = $customFieldCache[$boardId]['lookup'] ?? [];
                $remakeFieldId = $customFieldCache[$boardId]['remakeLabelField'] ?? null;
                if ($remakeFieldId) {
                    $remakeLabel = $reader->resolveDropdownText($card, $remakeFieldId, $lookup);
                }
            }

            $normalizedRemake = $this->normalizeLabel((string) ($remakeLabel ?? ''));
            $isRemove = $normalizedRemake !== '' && array_key_exists($normalizedRemake, $removeMap);

            $trelloLabel = $remakeLabel ? trim((string) $remakeLabel) : null;
            if ($remake->trello_reason_label !== $trelloLabel) {
                $updates['trello_reason_label'] = $trelloLabel;
                $updates['trello_reason_set_at'] = $trelloLabel ? $now : null;
            }

            $nextReason = $isRemove ? null : ($remakeLabel ? trim((string) $remakeLabel) : null);
            if ($remake->reason_label !== $nextReason) {
                $updates['reason_label'] = $nextReason;
                $updates['reason_label_color'] = null;
                $updates['reason_set_at'] = $nextReason ? $now : null;
            }

            $nextRemove = $isRemove ? trim((string) $remakeLabel) : null;
            if ($remake->label_name !== $nextRemove) {
                $updates['label_name'] = $nextRemove;
                $updates['label_points'] = $nextRemove ? ($removeMap[$normalizedRemake] ?? null) : null;
                $updates['label_set_at'] = $nextRemove ? $now : null;
            }

            if ($boardId) {
                $estimationFieldId = $customFieldCache[$boardId]['estimationField'] ?? null;
                $lookup = $customFieldCache[$boardId]['lookup'] ?? [];
                $estLabel = $estimationFieldId
                    ? $reader->resolveDropdownText($card, $estimationFieldId, $lookup)
                    : null;
                $estPoints = $pointsResolver->pointsForLabel($estLabel);

                $updates['estimate_points'] = $estPoints;
            }

            if ($updates !== []) {
                $updates['last_seen_at'] = $now;
                $remake->fill($updates)->save();
                $updated++;
            }
        }

        $this->info("Updated {$updated} remakes.");
        return Command::SUCCESS;
    }

    private function resolveRemakeLabelFieldId(TrelloSprintBoardReader $reader, array $customFields): ?string
    {
        $fieldName = (string) config('trello_sync.sprint_board.remake_label_field_name', 'Remake Label');
        if ($fieldName === '') {
            return null;
        }
        return $reader->findCustomFieldIdByName($customFields, $fieldName);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchCardsBatch(TrelloClient $trello, array $cardIds): array
    {
        $cardIds = array_values(array_filter(array_map('trim', $cardIds)));
        if ($cardIds === []) {
            return [];
        }

        $results = [];
        $urls = array_map(function ($id) {
            return "/cards/{$id}?fields=name,dateLastActivity,labels&customFieldItems=true";
        }, $cardIds);

        try {
            $batch = $trello->batch($urls);
        } catch (\Throwable $e) {
            $this->warn("Trello batch fetch failed: {$e->getMessage()}");
            return [];
        }

        foreach ($batch as $entry) {
            $url = Arr::get($entry, 'url');
            $code = Arr::get($entry, 'code');
            $body = Arr::get($entry, 'body');
            if (!is_string($url) || (int) $code !== 200 || !is_array($body)) {
                continue;
            }

            if (preg_match('/\\/cards\\/([^\\?]+)/', $url, $matches) !== 1) {
                continue;
            }
            $cardId = $matches[1] ?? null;
            if (!$cardId) {
                continue;
            }
            $results[$cardId] = $body;
        }

        return $results;
    }

    /**
     * @param array<string, int|float|string> $labels
     * @return array<string, int>
     */
    private function normalizeLabelPoints(array $labels): array
    {
        $out = [];
        foreach ($labels as $label => $points) {
            $name = $this->normalizeLabel((string) $label);
            if ($name === '') {
                continue;
            }
            $out[$name] = (int) $points;
        }
        return $out;
    }

    private function normalizeLabel(string $label): string
    {
        $label = strtolower(trim($label));
        $label = preg_replace('/^rm\\s*[:\\-]?\\s*/i', '', $label);
        return trim((string) $label);
    }
}
