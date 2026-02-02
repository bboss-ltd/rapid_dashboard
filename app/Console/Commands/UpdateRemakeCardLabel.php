<?php

namespace App\Console\Commands;

use App\Models\SprintRemake;
use App\Services\Trello\TrelloClient;
use App\Services\Trello\TrelloSprintBoardReader;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class UpdateRemakeCardLabel extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'trello:sync-remake-card-label {remakeId} {label}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Allows us to trigger a specific reconciliation update for a remake to ensure we get accurate remake reason reporting';

    /**
     * Execute the console command.
     */
    public function handle(TrelloClient $trello, TrelloSprintBoardReader $reader)
    {
        $remakeId = (int) $this->argument('remakeId');
        $remake = SprintRemake::query()->find($remakeId);
        if (!$remake) {
            $this->error("Sprint remake {$remakeId} not found.");
            return Command::FAILURE;
        }

        $labelArg = $this->argument('label');

        $labelName = null;

        if (is_string($labelArg) && trim($labelArg) !== '') {
            $labelName = $this->resolveLabelFromOptions($reader, $remake->sprint?->trello_board_id, trim($labelArg)) ?? trim($labelArg);
        } else {
            $labelName = $this->resolveLabelFromTrello($reader, $trello, $remake->trello_card_id, $remake->sprint?->trello_board_id);
        }

        if (!$labelName) {
            $this->warn('No matching remake reason label found.');
            return Command::SUCCESS;
        }

        $removeMap = $this->normalizeLabelPoints(config('trello_sync.remake_label_actions.remove', []));
        $normalized = $this->normalizeLabel((string) $labelName);

        if ($normalized !== '' && array_key_exists($normalized, $removeMap)) {
            $remake->label_name = $labelName;
            $remake->label_points = $removeMap[$normalized] ?? null;
            $remake->label_set_at = Carbon::now();
            $remake->reason_label = null;
            $remake->reason_label_color = null;
            $remake->reason_set_at = null;
        } else {
            $remake->reason_label = $labelName;
            $remake->reason_label_color = null;
            $remake->reason_set_at = Carbon::now();
            $remake->label_name = null;
            $remake->label_points = null;
            $remake->label_set_at = null;
        }

        $remake->trello_reason_label = $labelName;
        $remake->trello_reason_set_at = Carbon::now();
        $remake->last_seen_at = Carbon::now();
        $remake->save();

        $this->info("Updated remake {$remakeId} reason label to: {$labelName}");
        return Command::SUCCESS;
    }

    private function handleNonLabelledRemakes()
    {
        $remakes = SprintRemake::query()
            ->whereNull('label_name')
            ->whereNull('removed_at')
            ->get();


    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function resolveLabelFromTrello(TrelloSprintBoardReader $reader, TrelloClient $trello, ?string $cardId, ?string $boardId): ?string
    {
        if (!$cardId || !$boardId) {
            return null;
        }

        $card = $trello->get("/cards/{$cardId}", [
            'fields' => 'name',
            'customFieldItems' => 'true',
        ]);

        $customFields = $reader->fetchCustomFields($boardId);
        $lookup = $reader->buildDropdownLookup($customFields);
        $fieldId = $this->resolveRemakeLabelFieldId($reader, $customFields);
        if (!$fieldId) {
            return null;
        }

        return $reader->resolveDropdownText($card, $fieldId, $lookup);
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

    private function resolveLabelFromOptions(TrelloSprintBoardReader $reader, ?string $boardId, string $requestedLabel): ?string
    {
        $requestedLabel = trim($requestedLabel);
        if ($requestedLabel === '') {
            return null;
        }

        if (!$boardId) {
            return $requestedLabel;
        }

        $customFields = $reader->fetchCustomFields($boardId);
        $fieldId = $this->resolveRemakeLabelFieldId($reader, $customFields);
        if (!$fieldId) {
            return $requestedLabel;
        }

        foreach (($customFields ?? []) as $field) {
            if (($field['id'] ?? null) !== $fieldId) {
                continue;
            }
            foreach (($field['options'] ?? []) as $option) {
                $value = trim((string) ($option['value']['text'] ?? ''));
                if ($value !== '' && mb_strtolower($value) === mb_strtolower($requestedLabel)) {
                    return $value;
                }
            }
        }

        return $requestedLabel;
    }

    private function normalizeLabel(string $label): string
    {
        $label = strtolower(trim($label));
        $label = preg_replace('/^rm\\s*[:\\-]?\\s*/i', '', $label);
        return trim((string) $label);
    }

    private function resolveRemakeLabelFieldId(TrelloSprintBoardReader $reader, array $customFields): ?string
    {
        $fieldName = (string) config('trello_sync.sprint_board.remake_label_field_name', 'Remake Label');
        if ($fieldName === '') {
            return null;
        }
        return $reader->findCustomFieldIdByName($customFields, $fieldName);
    }
}
