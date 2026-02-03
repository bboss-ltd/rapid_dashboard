<?php

namespace App\Livewire\Wallboard;

use App\Models\Sprint;
use App\Services\FourJaw\FourJawService;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\On;
use Livewire\Component;
use Throwable;

class UtilisationCard extends Component
{
    public Sprint $sprint;
    public int $refreshSeconds = 60;
    public int $refreshTick = 0;
    public bool $debug = false;
    public ?string $lastRenderedAt = null;

    public function mount(Sprint $sprint, int $refreshSeconds = 60): void
    {
        $this->sprint = $sprint;
        $this->refreshSeconds = $refreshSeconds;
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

    public function render(FourJawService $fourjaw)
    {
        $this->lastRenderedAt = now()->toIso8601String();
        $ttl = (int) config('wallboard.cache_ttl_seconds', 300);
        $cacheEnabled = (bool) config('wallboard.cache_enabled', true);
        $resolver = function () use ($fourjaw) {
            $utilisationSummary = [
                'total_percent' => null,
                'per_machine' => [],
                'range' => null,
                'per_machine_range' => null,
            ];

            try {
                $machineIds = $fourjaw->getMachineIds();
                $utilCfg = config('wallboard.utilisation', []);
                $summaryDays = (int) ($utilCfg['summary_days'] ?? 7);
                $summaryShifts = (string) ($utilCfg['summary_shifts'] ?? 'on_shift');
                $debugUtil = (bool) ($utilCfg['debug'] ?? false);

                [$summaryStart, $summaryEnd] = $this->lastWorkingDaysRange($summaryDays);
                $summary = $fourjaw->getUtilisationSummary($summaryStart, $summaryEnd, $machineIds, $summaryShifts);

                $utilisationSummary = [
                    'total_percent' => Arr::get($summary, 'total_utilisation_percent'),
                    'per_machine' => [],
                    'range' => [
                        'start' => $summaryStart->toIso8601String(),
                        'end' => $summaryEnd->toIso8601String(),
                    ],
                    'per_machine_range' => null,
                ];

                if ($debugUtil) {
                    Log::info('FourJaw utilisation summary debug', [
                        'sprint_id' => $this->sprint->id,
                        'summary_range' => $utilisationSummary['range'],
                        'summary_shifts' => $summaryShifts,
                        'summary_total_percent' => $utilisationSummary['total_percent'],
                    ]);
                }
            } catch (Throwable $e) {
                $utilisationSummary = [
                    'total_percent' => null,
                    'per_machine' => [],
                    'range' => null,
                    'per_machine_range' => null,
                ];
                Log::warning('FourJaw utilisation fetch failed', [
                    'sprint_id' => $this->sprint->id,
                    'error' => $e->getMessage(),
                ]);
            }

            return $utilisationSummary;
        };
        if ($cacheEnabled && $ttl > 0) {
            $utilisationSummary = Cache::remember($this->cacheKey('utilisation'), max(5, $ttl), $resolver);
        } else {
            $utilisationSummary = $resolver();
        }

        return view('livewire.wallboard.utilisation-card', [
            'utilisation' => $utilisationSummary,
        ]);
    }

    private function cacheKey(string $suffix): string
    {
        return "wallboard:{$this->sprint->id}:{$suffix}";
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function lastWorkingDaysRange(int $days): array
    {
        if ($days <= 0) {
            $days = 7;
        }
        $workingDays = config('wallboard.burndown.working_days', [1, 2, 3, 4, 5]);
        $workingDays = array_map('intval', is_array($workingDays) ? $workingDays : []);

        $cursor = now()->subDay()->startOfDay();
        while (!in_array($cursor->isoWeekday(), $workingDays, true)) {
            $cursor->subDay();
        }

        $endDay = $cursor->copy();
        $count = 1;

        while ($count < $days) {
            $cursor->subDay();
            if (!in_array($cursor->isoWeekday(), $workingDays, true)) {
                continue;
            }
            $count++;
        }

        $start = $cursor->copy()->startOfDay();
        $end = $endDay->copy()->endOfDay();

        return [$start, $end];
    }
}
