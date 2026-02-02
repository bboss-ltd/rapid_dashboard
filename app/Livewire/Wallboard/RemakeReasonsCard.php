<?php

namespace App\Livewire\Wallboard;

use App\Domains\Wallboard\Repositories\RemakeStatsRepository;
use App\Models\Sprint;
use Livewire\Attributes\On;
use Livewire\Component;

class RemakeReasonsCard extends Component
{
    public Sprint $sprint;
    public int $refreshSeconds = 60;
    public int $refreshTick = 0;

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

    public function render(RemakeStatsRepository $remakeStats)
    {
        $reasonStart = now()->startOfDay();
        $reasonEnd = now()->endOfDay();
        $remakeReasonStats = $remakeStats->buildRemakeReasonStats($this->sprint, $reasonStart, $reasonEnd);

        return view('livewire.wallboard.remake-reasons-card', [
            'remakeReasonStats' => $remakeReasonStats,
        ]);
    }
}
