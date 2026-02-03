<?php

namespace App\Livewire\Wallboard;

use App\Domains\Wallboard\Actions\SyncWallboardAction;
use App\Models\Sprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;
use Livewire\Attributes\On;

class Header extends Component
{
    public Sprint $sprint;
    public int $refreshSeconds = 60;
    public bool $debug = false;
    public ?string $lastSyncedAt = null;

    public function mount(Sprint $sprint, int $refreshSeconds = 60): void
    {
        $this->sprint = $sprint;
        $this->refreshSeconds = $refreshSeconds;
        $this->lastSyncedAt = Carbon::now()->toIso8601String();
    }

    public function sync(SyncWallboardAction $syncWallboard): void
    {
        $this->dispatch('wallboard-syncing');
        $syncWallboard->run($this->sprint);
        $this->clearWallboardCache();
        $this->lastSyncedAt = Carbon::now()->toIso8601String();
        $this->dispatch('wallboard-manual-refresh');
        $this->dispatch('wallboard-refresh');
        $this->dispatch('wallboard-synced');
    }

    #[On('wallboard-refresh')]
    public function refresh(): void
    {
        $this->lastSyncedAt = Carbon::now()->toIso8601String();
    }

    public function render()
    {
        $this->sprint = $this->sprint->fresh() ?? $this->sprint;
        return view('livewire.wallboard.header');
    }

    private function clearWallboardCache(): void
    {
        $prefix = "wallboard:{$this->sprint->id}:";
        foreach (['burndown', 'machines', 'utilisation'] as $key) {
            Cache::forget($prefix . $key);
        }

        Cache::forget($prefix . 'remakes');
        Cache::forget($prefix . 'remakes:now');
        Cache::forget($prefix . 'reasons');
        Cache::forget($prefix . 'reasons:' . now()->toDateString());
    }
}
