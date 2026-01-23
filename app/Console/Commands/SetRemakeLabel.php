<?php

namespace App\Console\Commands;

use App\Models\Sprint;
use App\Models\SprintRemake;
use App\Services\Trello\TrelloClient;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

class SetRemakeLabel extends Command
{
    protected $signature = 'trello:remake-labels:set {remakeId} {label}';

    protected $description = 'Sets a remake label on Trello and syncs the local sprint remake record.';

    public function handle(TrelloClient $trello): int
    {
        $remakeId = (int) $this->argument('remakeId');
        $labelArg = trim((string) $this->argument('label'));

        if ($labelArg === '') {
            $this->error('Label is required.');
            return Command::FAILURE;
        }

        $remake = SprintRemake::query()->find($remakeId);
        if (!$remake) {
            $this->error("Sprint remake {$remakeId} not found.");
            return Command::FAILURE;
        }

        if ($remake->removed_at) {
            $this->error('Remake was removed; refusing to set labels.');
            return Command::FAILURE;
        }

        $sprint = Sprint::query()->find($remake->sprint_id);
        if (!$sprint || !$sprint->trello_board_id) {
            $this->error('Sprint or Trello board id missing for this remake.');
            return Command::FAILURE;
        }

        if (!$remake->trello_card_id) {
            $this->error('Remake is missing Trello card id.');
            return Command::FAILURE;
        }

        if ($remake->reason_label || $remake->label_name) {
            $this->error('Remake already has a label locally. Use Trello to change it, then sync.');
            return Command::FAILURE;
        }

        $labelRoles = $this->resolveLabelRoles($labelArg);
        if (!$labelRoles['valid']) {
            $this->error('Label is not configured as a remake label.');
            return Command::FAILURE;
        }

        $boardLabels = $this->fetchBoardLabels($trello, $sprint->trello_board_id);
        $boardLabel = $this->findLabelOnBoard($boardLabels, $labelArg);
        if (!$boardLabel) {
            $this->error('Label not found on Trello board.');
            return Command::FAILURE;
        }

        $labelId = Arr::get($boardLabel, 'id');
        if (!is_string($labelId) || $labelId === '') {
            $this->error('Unable to resolve Trello label id.');
            return Command::FAILURE;
        }

        $cardLabels = $this->fetchCardLabels($trello, $remake->trello_card_id);
        if ($this->cardHasRemakeLabel($cardLabels)) {
            $this->error('Card already has a remake label. Use Trello to change it, then sync.');
            return Command::FAILURE;
        }
        $cardLabelIds = array_filter(array_map(function ($label) {
            return Arr::get($label, 'id');
        }, $cardLabels));

        if (!in_array($labelId, $cardLabelIds, true)) {
            $trello->post("/cards/{$remake->trello_card_id}/idLabels", [
                'value' => $labelId,
            ]);
        }

        $labelName = trim((string) Arr::get($boardLabel, 'name', $labelArg));
        $labelColor = trim((string) Arr::get($boardLabel, 'color', ''));

        $now = Carbon::now();
        $updates = [
            'last_seen_at' => $now,
        ];

        if ($labelRoles['reason']) {
            $updates['reason_label'] = $labelName;
            $updates['reason_label_color'] = $labelColor;
            $updates['reason_set_at'] = $now;
        }

        if ($labelRoles['remove']) {
            $updates['label_name'] = $labelName;
            $updates['label_points'] = $labelRoles['points'];
            $updates['label_set_at'] = $now;
        }

        if ($labelRoles['restore']) {
            $updates['label_name'] = null;
            $updates['label_points'] = null;
            $updates['label_set_at'] = null;
        }

        $remake->fill($updates)->save();

        $this->info("Updated remake {$remakeId} with label {$labelName}.");
        return Command::SUCCESS;
    }

    /**
     * @return array{valid:bool, reason:bool, remove:bool, restore:bool, points:int|null}
     */
    private function resolveLabelRoles(string $label): array
    {
        $normalized = $this->normalizeLabel($label);
        $reasonLabels = $this->buildNormalizedLabelMap(config('trello_sync.remake_reason_labels', []));
        $removeMap = $this->normalizeLabelPoints(config('trello_sync.remake_label_actions.remove', []));
        $restoreLabels = $this->normalizeLabels(config('trello_sync.remake_label_actions.restore', []));

        $isReason = isset($reasonLabels[$normalized]);
        $isRemove = array_key_exists($normalized, $removeMap);
        $isRestore = in_array($normalized, $restoreLabels, true);

        return [
            'valid' => $isReason || $isRemove || $isRestore,
            'reason' => $isReason,
            'remove' => $isRemove,
            'restore' => $isRestore,
            'points' => $isRemove ? $removeMap[$normalized] : null,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchBoardLabels(TrelloClient $trello, string $boardId): array
    {
        $labels = $trello->get("/boards/{$boardId}/labels", [
            'limit' => 1000,
            'fields' => 'name,color',
        ]);

        return is_array($labels) ? $labels : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findLabelOnBoard(array $labels, string $label): ?array
    {
        $normalized = $this->normalizeLabel($label);
        foreach ($labels as $row) {
            $name = trim((string) Arr::get($row, 'name', ''));
            if ($name === '') {
                continue;
            }
            if ($this->normalizeLabel($name) === $normalized) {
                return $row;
            }
        }
        return null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchCardLabels(TrelloClient $trello, string $cardId): array
    {
        try {
            $card = $trello->get("/cards/{$cardId}", [
                'fields' => 'labels',
            ]);
        } catch (\Throwable $e) {
            $this->warn("Trello fetch failed for card {$cardId}: {$e->getMessage()}");
            return [];
        }

        $labels = Arr::get($card, 'labels', []);
        return is_array($labels) ? $labels : [];
    }

    private function cardHasRemakeLabel(array $labels): bool
    {
        $reasonLabels = $this->buildNormalizedLabelMap(config('trello_sync.remake_reason_labels', []));
        $removeMap = $this->normalizeLabelPoints(config('trello_sync.remake_label_actions.remove', []));
        $restoreLabels = $this->normalizeLabels(config('trello_sync.remake_label_actions.restore', []));

        foreach ($labels as $label) {
            $name = trim((string) Arr::get($label, 'name', ''));
            if ($name === '') {
                continue;
            }
            $normalized = $this->normalizeLabel($name);
            if (isset($reasonLabels[$normalized])) {
                return true;
            }
            if (array_key_exists($normalized, $removeMap)) {
                return true;
            }
            if (in_array($normalized, $restoreLabels, true)) {
                return true;
            }
        }

        return false;
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
     * @param array<int, string> $labels
     * @return array<int, string>
     */
    private function normalizeLabels(array $labels): array
    {
        return array_values(array_filter(array_map(function ($label) {
            $label = $this->normalizeLabel((string) $label);
            return $label !== '' ? $label : null;
        }, $labels)));
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
