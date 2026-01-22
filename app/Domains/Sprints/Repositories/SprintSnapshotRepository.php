<?php

namespace App\Domains\Sprints\Repositories;

use App\Models\Sprint;
use App\Models\SprintSnapshot;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class SprintSnapshotRepository
{
    public function paginatedBySprint(Sprint $sprint, int $perPage = 10): LengthAwarePaginator
    {
        return $sprint->snapshots()
            ->orderByDesc('taken_at')
            ->paginate($perPage);
    }

    public function latestByType(Sprint $sprint, string $type): ?SprintSnapshot
    {
        return $sprint->snapshots()
            ->where('type', $type)
            ->latest('taken_at')
            ->first();
    }

    public function latest(Sprint $sprint): ?SprintSnapshot
    {
        return $sprint->snapshots()
            ->latest('taken_at')
            ->first();
    }

    public function paginatedCards(SprintSnapshot $snapshot, int $perPage = 25, string $pageName = 'cardsPage'):
        LengthAwarePaginator
    {
        return $snapshot->cards()
            ->with('card')
            ->orderByDesc('is_done')
            ->paginate($perPage, ['*'], $pageName);
    }

    public function hasSnapshotType(Sprint $sprint, string $type): bool
    {
        return $sprint->snapshots()->where('type', $type)->exists();
    }
}
