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

class MachinesCard extends Component
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
        $ttl = max(5, (int) config('wallboard.cache_ttl_seconds', 300));
        $payload = Cache::remember($this->cacheKey('machines'), $ttl, function () use ($fourjaw) {
            $machines = [];
            $utilisationSummary = [
                'total_percent' => null,
                'per_machine' => [],
                'range' => null,
                'per_machine_range' => null,
            ];

            try {
                $machines = $fourjaw->getCurrentStatuses();
            } catch (Throwable $e) {
                $machines = [];
                Log::warning('FourJaw machine status fetch failed', [
                    'sprint_id' => $this->sprint->id,
                    'error' => $e->getMessage(),
                ]);
            }

            try {
                $machineIds = $fourjaw->getMachineIds();
                $utilCfg = config('wallboard.utilisation', []);
                $summaryDays = (int) ($utilCfg['summary_days'] ?? 7);
                $summaryShifts = (string) ($utilCfg['summary_shifts'] ?? 'on_shift');
                $machineShifts = (string) ($utilCfg['per_machine_shifts'] ?? 'on_shift');
                $debugUtil = (bool) ($utilCfg['debug'] ?? false);

                [$summaryStart, $summaryEnd] = $this->lastWorkingDaysRange($summaryDays);
                $summary = $fourjaw->getUtilisationSummary($summaryStart, $summaryEnd, $machineIds, $summaryShifts);

                $dayStart = now()->startOfDay();
                $dayEnd = now();
                $daySummary = $fourjaw->getUtilisationSummary($dayStart, $dayEnd, $machineIds, $machineShifts);

                $dayAssetAverages = Arr::get($daySummary, 'asset_averages', []);
                $dayPerMachine = [];
                if (is_array($dayAssetAverages)) {
                    foreach ($dayAssetAverages as $row) {
                        $assetId = Arr::get($row, 'asset_id');
                        if (!is_string($assetId) || $assetId === '') {
                            continue;
                        }
                        $dayPerMachine[$assetId] = Arr::get($row, 'utilisation_percent');
                    }
                }

                $utilisationSummary = [
                    'total_percent' => Arr::get($summary, 'total_utilisation_percent'),
                    'per_machine' => $dayPerMachine,
                    'range' => [
                        'start' => $summaryStart->toIso8601String(),
                        'end' => $summaryEnd->toIso8601String(),
                    ],
                    'per_machine_range' => [
                        'start' => $dayStart->toIso8601String(),
                        'end' => $dayEnd->toIso8601String(),
                    ],
                ];

                if ($debugUtil) {
                    Log::info('FourJaw utilisation summary debug', [
                        'sprint_id' => $this->sprint->id,
                        'summary_range' => $utilisationSummary['range'],
                        'summary_shifts' => $summaryShifts,
                        'summary_total_percent' => $utilisationSummary['total_percent'],
                        'summary_assets' => is_array(Arr::get($summary, 'asset_averages', [])) ? count(Arr::get($summary, 'asset_averages', [])) : 0,
                        'machine_range' => $utilisationSummary['per_machine_range'],
                        'machine_shifts' => $machineShifts,
                        'machine_assets' => is_array($dayAssetAverages) ? count($dayAssetAverages) : 0,
                        'machine_ids' => $machineIds,
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

            return [
                'machines' => $machines,
                'utilisation' => $utilisationSummary,
            ];
        });

        return view('livewire.wallboard.machines-card', [
            'machines' => $payload['machines'],
            'utilisation' => $payload['utilisation'],
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
