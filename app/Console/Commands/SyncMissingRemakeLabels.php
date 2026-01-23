<?php

namespace App\Console\Commands;

use App\Models\Sprint;
use App\Models\SprintRemake;
use App\Services\Trello\TrelloClient;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

class SyncMissingRemakeLabels extends Command
{
    protected $signature = 'trello:remake-labels:sync-missing {--sprint=} {--limit=}';

    protected $description = 'Backfills missing remake labels from Trello for existing sprint remakes.';

    public function handle(TrelloClient $trello): int
    {
        $sprintId = $this->option('sprint');
        $limit = (int) ($this->option('limit') ?? 0);

        $query = SprintRemake::query()
            ->whereNull('removed_at')
            ->where(function ($q) {
                $q->whereNull('reason_label')
                    ->orWhereNull('label_name');
            });

        if ($sprintId) {
            $query->where('sprint_id', (int) $sprintId);
        }

        if ($limit > 0) {
            $query->limit($limit);
        }

        $remakes = $query->orderBy('id')->get();
        if ($remakes->isEmpty()) {
            $this->info('No remakes missing labels.');
            return Command::SUCCESS;
        }

        $reasonLabels = config('trello_sync.remake_reason_labels', []);
        $reasonMap = $this->buildNormalizedLabelMap($reasonLabels);

        $removeMap = $this->normalizeLabelPoints(config('trello_sync.remake_label_actions.remove', []));
        $removeLabels = array_keys($removeMap);

        $now = Carbon::now();
        $updated = 0;

        $labelLookup = $this->fetchLabelsBatch($trello, $remakes->pluck('trello_card_id')->filter()->all());

        foreach ($remakes as $remake) {
            if (!$remake->trello_card_id) {
                $this->warn("Remake {$remake->id} missing Trello card id; skipping.");
                continue;
            }

            $labels = $labelLookup[$remake->trello_card_id] ?? [];
            if ($labels === []) {
                continue;
            }

            $updates = [];

            if ($remake->reason_label === null) {
                foreach ($labels as $label) {
                    $name = trim((string) Arr::get($label, 'name', ''));
                    if ($name === '') {
                        continue;
                    }
                    $normalized = $this->normalizeLabel($name);
                    if (isset($reasonMap[$normalized])) {
                        $updates['reason_label'] = $name;
                        $updates['reason_label_color'] = (string) Arr::get($label, 'color', '');
                        $updates['reason_set_at'] = $now;
                        break;
                    }
                }
            }

            if ($remake->label_name === null) {
                foreach ($labels as $label) {
                    $name = trim((string) Arr::get($label, 'name', ''));
                    if ($name === '') {
                        continue;
                    }
                    $normalized = $this->normalizeLabel($name);
                    if (in_array($normalized, $removeLabels, true)) {
                        $updates['label_name'] = $name;
                        $updates['label_points'] = $removeMap[$normalized];
                        $updates['label_set_at'] = $now;
                        break;
                    }
                }
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

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchLabelsBatch(TrelloClient $trello, array $cardIds): array
    {
        $cardIds = array_values(array_filter(array_map('trim', $cardIds)));
        if ($cardIds === []) {
            return [];
        }

        $results = [];
        $urls = array_map(function ($id) {
            return "/cards/{$id}?fields=labels";
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
            $labels = Arr::get($body, 'labels', []);
            $results[$cardId] = is_array($labels) ? $labels : [];
        }

        return $results;
    }

    /**
     * @param array<int, string> $labels
     * @return array<string, string>
     */
    private function buildNormalizedLabelMap(array $labels): array
    {
        $map = [];
        foreach ($labels as $label) {
            $name = trim((string) $label);
            if ($name === '') {
                continue;
            }
            $map[$this->normalizeLabel($name)] = $name;
        }
        return $map;
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
