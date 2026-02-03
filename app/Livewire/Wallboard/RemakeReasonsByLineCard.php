<?php

namespace App\Livewire\Wallboard;

use App\Domains\Wallboard\Repositories\RemakeStatsRepository;
use App\Models\Sprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use App\Services\Trello\TrelloSprintBoardReader;
use Livewire\Attributes\On;
use Livewire\Component;

class RemakeReasonsByLineCard extends Component
{
    public Sprint $sprint;
    public int $refreshSeconds = 60;
    public int $refreshTick = 0;
    public bool $debug = false;
    public ?string $lastRenderedAt = null;
    public ?string $remakesFor = null;

    public function mount(Sprint $sprint, int $refreshSeconds = 60, ?string $remakesFor = null): void
    {
        $this->sprint = $sprint;
        $this->refreshSeconds = $refreshSeconds;
        $this->remakesFor = $remakesFor;
    }

    #[On('wallboard-refresh')]
    public function refresh(): void
    {
        $this->refreshTick++;
    }

    #[On('wallboard-manual-refresh')]
    public function refreshFromManual(): void
    {
        $this->refreshTick++;
    }

    public function render(RemakeStatsRepository $remakeStats, TrelloSprintBoardReader $reader)
    {
        $this->lastRenderedAt = now()->toIso8601String();
        [$reasonStart, $reasonEnd] = $this->resolveReasonRange();
        $ttl = max(5, (int) config('wallboard.cache_ttl_seconds', 300));
        $lineOptions = $this->resolveProductionLineOptions($reader);
        $payload = Cache::remember($this->cacheKey('reasons-by-line', $reasonStart->toDateString()), $ttl, function () use ($remakeStats, $reasonStart, $reasonEnd) {
            return $remakeStats->buildRemakeReasonByLineStats($this->sprint, $reasonStart, $reasonEnd);
        });

        return view('livewire.wallboard.remake-reasons-by-line-card', [
            'reasonByLine' => $payload,
            'reasonDate' => $reasonStart,
            'lineOptions' => $lineOptions,
        ]);
    }

    private function cacheKey(string $suffix, ?string $variant = null): string
    {
        $variant = $variant ? ':' . $variant : '';
        return "wallboard:{$this->sprint->id}:{$suffix}{$variant}";
    }

    /**
     * @return array{0: \Illuminate\Support\Carbon, 1: \Illuminate\Support\Carbon}
     */
    private function resolveReasonRange(): array
    {
        $reasonDay = null;
        if ($this->remakesFor) {
            try {
                $reasonDay = Carbon::createFromFormat('Y-m-d', $this->remakesFor);
            } catch (\Throwable) {
                $reasonDay = null;
            }
        }

        $reasonDay = $reasonDay ?: now();
        $reasonStart = $reasonDay->copy()->startOfDay();
        $reasonEnd = $reasonDay->copy()->endOfDay();

        return [$reasonStart, $reasonEnd];
    }

    /**
     * @return array<int, string>
     */
    private function resolveProductionLineOptions(TrelloSprintBoardReader $reader): array
    {
        $boardId = $this->sprint->trello_board_id;
        if (!$boardId) {
            return [];
        }

        $fieldName = (string) config('trello_sync.sprint_board.production_line_field_name', 'Production Line');
        if ($fieldName === '') {
            return [];
        }

        $cacheKey = "trello.board.custom_fields.{$boardId}";
        $customFields = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($reader, $boardId) {
            return $reader->fetchCustomFields($boardId);
        });

        $fieldId = $reader->findCustomFieldIdByName($customFields, $fieldName);
        if (!$fieldId) {
            return [];
        }

        foreach ($customFields as $field) {
            if (($field['id'] ?? null) !== $fieldId) {
                continue;
            }
            $options = [];
            foreach (($field['options'] ?? []) as $option) {
                $value = trim((string) ($option['value']['text'] ?? ''));
                if ($value !== '') {
                    $options[] = $value;
                }
            }
            return $options;
        }

        return [];
    }
}
