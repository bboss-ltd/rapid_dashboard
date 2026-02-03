<?php

namespace App\Livewire\Wallboard;

use App\Domains\Reporting\Queries\BurndownSeriesQuery;
use App\Models\Sprint;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\On;
use Livewire\Component;

class BurndownCard extends Component
{
    public Sprint $sprint;
    public array $types = ['ad_hoc', 'end'];
    public int $refreshSeconds = 60;
    public int $refreshTick = 0;
    public bool $debug = false;
    public ?string $lastRenderedAt = null;

    public function mount(Sprint $sprint, array $types = ['ad_hoc', 'end'], int $refreshSeconds = 60): void
    {
        $this->sprint = $sprint;
        $this->types = $types;
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

    public function render(BurndownSeriesQuery $burndownQuery)
    {
        $this->lastRenderedAt = now()->toIso8601String();
        $ttl = (int) config('wallboard.cache_ttl_seconds', 300);
        $cacheEnabled = (bool) config('wallboard.cache_enabled', true);
        $resolver = function () use ($burndownQuery) {
            return [
                'series' => $burndownQuery->run($this->sprint, $this->types)->values(),
                'cfg' => config('wallboard.burndown', []),
            ];
        };
        if ($cacheEnabled && $ttl > 0) {
            $payload = Cache::remember($this->cacheKey('burndown'), max(5, $ttl), $resolver);
        } else {
            $payload = $resolver();
        }

        return view('livewire.wallboard.burndown-card', [
            'series' => $payload['series'],
            'cfg' => $payload['cfg'],
            'sprint' => $this->sprint,
        ]);
    }

    private function cacheKey(string $suffix): string
    {
        return "wallboard:{$this->sprint->id}:{$suffix}";
    }
}
