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
        $reasonStart = $this->sprint->starts_at
            ? $this->sprint->starts_at->copy()->startOfDay()
            : now()->startOfDay();
        $latest = $this->sprint->snapshots()->latest('taken_at')->value('taken_at');
        $reasonEnd = $latest
            ? \Illuminate\Support\Carbon::parse($latest)->endOfDay()
            : now()->endOfDay();
        if ($this->sprint->ends_at && $reasonEnd->greaterThan($this->sprint->ends_at)) {
            $reasonEnd = $this->sprint->ends_at->copy()->endOfDay();
        }

        $remakeReasonStats = $remakeStats->buildRemakeReasonStats($this->sprint, $reasonStart, $reasonEnd);

        return view('livewire.wallboard.remake-reasons-card', [
            'remakeReasonStats' => $remakeReasonStats,
        ]);
    }
}
