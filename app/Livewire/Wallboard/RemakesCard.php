<?php

namespace App\Livewire\Wallboard;

use App\Domains\Reporting\Queries\BurndownSeriesQuery;
use App\Domains\Wallboard\Repositories\RemakeStatsRepository;
use App\Models\Sprint;
use Livewire\Attributes\On;
use Livewire\Component;

class RemakesCard extends Component
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

    public function render(BurndownSeriesQuery $burndownQuery, RemakeStatsRepository $remakeStats)
    {
        $series = $burndownQuery->run($this->sprint, $this->types);
        $latestPoint = $series->last() ?? [];
        $remakeStatsData = $remakeStats->buildRemakeStats($this->sprint, $this->types);
        $liveRemakes = (int) ($latestPoint['remakes_count'] ?? 0);
        $remakeTotal = $remakeStatsData['total'] ?? $liveRemakes;

        return view('livewire.wallboard.remakes-card', [
            'remakeStats' => $remakeStatsData,
            'remakeTotal' => $remakeTotal,
        ]);
    }
}
