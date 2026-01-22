<?php

namespace App\Domains\Sprints\Actions;

use App\Domains\Sprints\Repositories\SprintRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class SearchSprintsAction
{
    public function __construct(private SprintRepository $sprints) {}

    public function run(string $status, string $search, int $perPage = 15): LengthAwarePaginator
    {
        return $this->sprints->searchWithStatus($status, $search, $perPage);
    }
}
