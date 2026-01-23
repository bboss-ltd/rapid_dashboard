<?php

namespace App\Console\Commands;

use App\Models\SprintRemake;
use App\Services\Trello\TrelloClient;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
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
    public function handle(TrelloClient $trello)
    {
        $remakeId = (int) $this->argument('remakeId');
        $remake = SprintRemake::query()->find($remakeId);
        if (!$remake) {
            $this->error("Sprint remake {$remakeId} not found.");
            return Command::FAILURE;
        }

        $labelArg = $this->argument('label');

        $labelName = null;
        $labelColor = null;

        if (is_string($labelArg) && trim($labelArg) !== '') {
            $labelName = trim($labelArg);
            [$labelName, $labelColor] = $this->resolveLabelFromCard($trello, $remake->trello_card_id, $labelName);
        } else {
            [$labelName, $labelColor] = $this->resolveLabelFromTrello($trello, $remake->trello_card_id);
        }

        if (!$labelName) {
            $this->warn('No matching remake reason label found.');
            return Command::SUCCESS;
        }

        $remake->reason_label = $labelName;
        $remake->reason_label_color = $labelColor;
        $remake->reason_set_at = Carbon::now();
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
    private function resolveLabelFromTrello(TrelloClient $trello, ?string $cardId): array
    {
        if (!$cardId) {
            return [null, null];
        }

        $labels = $this->fetchCardLabels($trello, $cardId);
        if ($labels === []) {
            return [null, null];
        }

        $reasonLabels = config('trello_sync.remake_reason_labels', []);
        $labelMap = $this->buildNormalizedLabelMap($reasonLabels);

        foreach ($labels as $label) {
            $name = trim((string) Arr::get($label, 'name', ''));
            if ($name === '') {
                continue;
            }
            $normalized = $this->normalizeLabel($name);
            if (isset($labelMap[$normalized])) {
                return [$name, (string) Arr::get($label, 'color', '')];
            }
        }

        return [null, null];
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function resolveLabelFromCard(TrelloClient $trello, ?string $cardId, string $requestedLabel): array
    {
        $requestedLabel = trim($requestedLabel);
        if ($requestedLabel === '') {
            return [null, null];
        }

        if (!$cardId) {
            return [$requestedLabel, null];
        }

        $labels = $this->fetchCardLabels($trello, $cardId);
        if ($labels === []) {
            return [$requestedLabel, null];
        }

        $normalizedRequested = $this->normalizeLabel($requestedLabel);
        foreach ($labels as $label) {
            $name = trim((string) Arr::get($label, 'name', ''));
            if ($name === '') {
                continue;
            }
            if ($this->normalizeLabel($name) === $normalizedRequested) {
                return [$name, (string) Arr::get($label, 'color', '')];
            }
        }

        return [$requestedLabel, null];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchCardLabels(TrelloClient $trello, string $cardId): array
    {
        $card = $trello->get("/cards/{$cardId}", [
            'fields' => 'labels',
        ]);

        $labels = Arr::get($card, 'labels', []);
        return is_array($labels) ? $labels : [];
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

    private function normalizeLabel(string $label): string
    {
        $label = strtolower(trim($label));
        $label = preg_replace('/^rm\\s*[:\\-]?\\s*/i', '', $label);
        return trim((string) $label);
    }
}
