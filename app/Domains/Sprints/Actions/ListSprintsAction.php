<?php

namespace App\Domains\Sprints\Actions;

use App\Domains\Sprints\Repositories\SprintRepository;
use Illuminate\Database\Eloquent\Collection;

final class ListSprintsAction
{
    public function __construct(private SprintRepository $sprints) {}

    public function run(): Collection
    {
        return $this->sprints->listAll();
    }
}
