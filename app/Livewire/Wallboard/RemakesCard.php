<?php

namespace App\Livewire\Wallboard;

use App\Domains\Reporting\Queries\BurndownSeriesQuery;
use App\Domains\Wallboard\Repositories\RemakeStatsRepository;
use App\Models\Sprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\On;
use Livewire\Component;

class RemakesCard extends Component
{
    public Sprint $sprint;
    public array $types = ['ad_hoc', 'end'];
    public int $refreshSeconds = 60;
    public int $refreshTick = 0;
    public bool $debug = false;
    public ?string $lastRenderedAt = null;
    public ?string $remakesFor = null;

    public function mount(Sprint $sprint, array $types = ['ad_hoc', 'end'], int $refreshSeconds = 60, ?string $remakesFor = null): void
    {
        $this->sprint = $sprint;
        $this->types = $types;
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

    public function render(BurndownSeriesQuery $burndownQuery, RemakeStatsRepository $remakeStats)
    {
        $this->lastRenderedAt = now()->toIso8601String();
        $remakesForDate = $this->resolveRemakesForDate();
        $ignoreSprint = $remakesForDate !== null;
        $ttl = (int) config('wallboard.cache_ttl_seconds', 300);
        $cacheEnabled = (bool) config('wallboard.cache_enabled', true);
        $cacheVariant = $remakesForDate ? $remakesForDate->toDateString() : 'now';
        $resolver = function () use ($burndownQuery, $remakeStats, $remakesForDate, $ignoreSprint) {
            $series = $burndownQuery->run($this->sprint, $this->types);
            $latestPoint = $series->last() ?? [];
            $remakeStatsData = $remakeStats->buildRemakeStats($this->sprint, $this->types, $remakesForDate, $ignoreSprint);
            $liveRemakes = (int) ($latestPoint['remakes_count'] ?? 0);
            $remakeTotal = $remakeStatsData['total'] ?? $liveRemakes;

            return [
                'remakeStats' => $remakeStatsData,
                'remakeTotal' => $remakeTotal,
            ];
        };
        if ($cacheEnabled && $ttl > 0) {
            $payload = Cache::remember($this->cacheKey('remakes', $cacheVariant), max(5, $ttl), $resolver);
        } else {
            $payload = $resolver();
        }

        return view('livewire.wallboard.remakes-card', [
            'remakeStats' => $payload['remakeStats'],
            'remakeTotal' => $payload['remakeTotal'],
            'remakesFor' => $remakesForDate?->toDateString(),
        ]);
    }

    private function cacheKey(string $suffix, ?string $variant = null): string
    {
        $variant = $variant ? ':' . $variant : '';
        return "wallboard:{$this->sprint->id}:{$suffix}{$variant}";
    }

    private function resolveRemakesForDate(): ?Carbon
    {
        if (!$this->remakesFor) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $this->remakesFor);
        } catch (\Throwable) {
            return null;
        }
    }
}
