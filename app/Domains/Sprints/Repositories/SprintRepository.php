<?php

namespace App\Domains\Sprints\Repositories;

use App\Models\Sprint;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

final class SprintRepository
{
    public function listActiveByStatus(): Collection
    {
        return Sprint::query()
            ->where('status', 'active')
            ->whereNull('closed_at')
            ->orderByDesc('starts_at')
            ->get();
    }

    public function findActiveByDates(): ?Sprint
    {
        return Sprint::query()->active()->first();
    }

    public function findLatestOpen(): ?Sprint
    {
        return Sprint::query()
            ->whereNull('closed_at')
            ->orderByDesc('starts_at')
            ->first();
    }

    public function findUpcoming(int $limit = 3): Collection
    {
        return Sprint::query()
            ->whereNull('closed_at')
            ->where('starts_at', '>', now())
            ->orderBy('starts_at')
            ->take($limit)
            ->get();
    }

    public function listAll(): Collection
    {
        return Sprint::query()
            ->orderByDesc('starts_at')
            ->get();
    }

    public function listAllByStartAsc(): Collection
    {
        return Sprint::query()
            ->orderBy('starts_at')
            ->get();
    }

    public function searchWithStatus(string $status, string $search, int $perPage = 15): LengthAwarePaginator
    {
        $query = Sprint::query()
            ->orderByDesc('starts_at')
            ->withCount('snapshots');

        if ($status === 'open') {
            $query->whereNull('closed_at');
        } elseif ($status === 'closed') {
            $query->whereNotNull('closed_at');
        }

        if ($search !== '') {
            $query->where('name', 'like', "%{$search}%");
        }

        return $query->paginate($perPage)->withQueryString();
    }
}
