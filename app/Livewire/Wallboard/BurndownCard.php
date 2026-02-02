<?php

namespace App\Livewire\Wallboard;

use App\Domains\Reporting\Queries\BurndownSeriesQuery;
use App\Models\Sprint;
use Livewire\Attributes\On;
use Livewire\Component;

class BurndownCard extends Component
{
    public Sprint $sprint;
    public array $types = ['ad_hoc', 'end'];
    public int $refreshSeconds = 60;
    public int $refreshTick = 0;

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

    public function render(BurndownSeriesQuery $burndownQuery)
    {
        $series = $burndownQuery->run($this->sprint, $this->types)->values();
        $cfg = config('wallboard.burndown', []);

        return view('livewire.wallboard.burndown-card', [
            'series' => $series,
            'cfg' => $cfg,
            'sprint' => $this->sprint,
        ]);
    }
}
