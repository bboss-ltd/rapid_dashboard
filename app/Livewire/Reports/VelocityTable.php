<?php

namespace App\Livewire\Reports;

use App\Domains\Reporting\Queries\VelocityBySprintQuery;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class VelocityTable extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $status = 'all'; // all|open|closed

    #[Url]
    public int $take = 20; // last N sprints (0 = all)

    #[Url]
    public string $sort = 'starts_at'; // starts_at|scope_points|completed_points|remaining_points|sprint_name|ends_at|closed_at

    #[Url]
    public string $dir = 'desc'; // asc|desc

    public int $perPage = 15;

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingStatus(): void { $this->resetPage(); }
    public function updatingTake(): void { $this->resetPage(); }

    public function sortBy(string $key): void
    {
        if ($this->sort === $key) {
            $this->dir = $this->dir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sort = $key;
            $this->dir = 'desc';
        }

        $this->resetPage();
    }

    public function render(VelocityBySprintQuery $query)
    {
        $rows = $query->run(); // Collection of arrays

        // filter: status
        if ($this->status === 'open') {
            $rows = $rows->filter(fn($r) => empty($r['closed_at']));
        } elseif ($this->status === 'closed') {
            $rows = $rows->filter(fn($r) => !empty($r['closed_at']));
        }

        // filter: search
        $q = trim($this->search);
        if ($q !== '') {
            $rows = $rows->filter(fn($r) => str_contains(mb_strtolower($r['sprint_name'] ?? ''), mb_strtolower($q)));
        }

        // sort
        $rows = $rows->sortBy(fn($r) => $this->sortValue($r, $this->sort), SORT_REGULAR, $this->dir === 'desc')
            ->values();

        // take last N (after sorting by starts_at typically; but we apply after sort so it matches the view)
        if ($this->take > 0) {
            $rows = $rows->take($this->take)->values();
        }

        $paginated = $this->paginateCollection($rows, $this->perPage);

        return view('livewire.reports.velocity-table', [
            'rows' => $paginated,
        ])->layout('components.layouts.app', ['title' => __('Velocity by Sprint')]);
    }

    private function sortValue(array $row, string $key): mixed
    {
        return match ($key) {
            'scope_points', 'completed_points', 'remaining_points' => (int) ($row[$key] ?? 0),
            'starts_at', 'ends_at', 'closed_at' => $row[$key] ?? '',
            'sprint_name' => mb_strtolower((string) ($row[$key] ?? '')),
            default => $row[$key] ?? '',
        };
    }

    private function paginateCollection(Collection $items, int $perPage): LengthAwarePaginator
    {
        $page = $this->getPage();
        $total = $items->count();
        $results = $items->slice(($page - 1) * $perPage, $perPage)->values();

        return new LengthAwarePaginator(
            $results,
            $total,
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }
}
