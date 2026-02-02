<?php

namespace App\Livewire\Wallboard;

use App\Domains\Wallboard\Actions\SyncWallboardAction;
use App\Models\Sprint;
use Illuminate\Support\Carbon;
use Livewire\Component;
use Livewire\Attributes\On;

class Header extends Component
{
    public Sprint $sprint;
    public int $refreshSeconds = 60;
    public ?string $lastSyncedAt = null;
    public ?string $statusMessage = null;

    public function mount(Sprint $sprint, int $refreshSeconds = 60): void
    {
        $this->sprint = $sprint;
        $this->refreshSeconds = $refreshSeconds;
        $this->lastSyncedAt = Carbon::now()->toIso8601String();
    }

    public function sync(SyncWallboardAction $syncWallboard): void
    {
        $syncWallboard->run($this->sprint);
        $this->lastSyncedAt = Carbon::now()->toIso8601String();
        $this->statusMessage = 'Sync complete.';
        $this->dispatch('wallboard-refresh');
    }

    #[On('wallboard-refresh')]
    public function refresh(): void
    {
        $this->lastSyncedAt = Carbon::now()->toIso8601String();
    }

    public function render()
    {
        return view('livewire.wallboard.header');
    }
}
