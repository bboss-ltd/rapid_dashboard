<?php

namespace App\Livewire\Wallboard;

use App\Domains\Wallboard\Repositories\RemakeStatsRepository;
use App\Models\Sprint;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\On;
use Livewire\Component;

class RemakeReasonsCard extends Component
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

    public function render(RemakeStatsRepository $remakeStats)
    {
        $this->lastRenderedAt = now()->toIso8601String();
        $ttl = (int) config('wallboard.cache_ttl_seconds', 300);
        $cacheEnabled = (bool) config('wallboard.cache_enabled', true);
        [$reasonStart, $reasonEnd] = $this->resolveReasonRange();
        $resolver = function () use ($remakeStats, $reasonStart, $reasonEnd) {
            $ignoreSprint = $this->remakesFor !== null;
            return $remakeStats->buildRemakeReasonStats($this->sprint, $reasonStart, $reasonEnd, $ignoreSprint);
        };
        if ($cacheEnabled && $ttl > 0) {
            $remakeReasonStats = Cache::remember($this->cacheKey('reasons', $reasonStart->toDateString()), max(5, $ttl), $resolver);
        } else {
            $remakeReasonStats = $resolver();
        }

        return view('livewire.wallboard.remake-reasons-card', [
            'remakeReasonStats' => $remakeReasonStats,
            'reasonDate' => $reasonStart,
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
                $reasonDay = \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $this->remakesFor);
            } catch (\Throwable) {
                $reasonDay = null;
            }
        }

        $reasonDay = $reasonDay ?: now();
        $reasonStart = $reasonDay->copy()->startOfDay();
        $reasonEnd = $reasonDay->copy()->endOfDay();

        return [$reasonStart, $reasonEnd];
    }
}
