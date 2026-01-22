<?php

namespace App\Domains\Sprints\Actions;

use App\Models\Sprint;

final class CreateSprintAction
{
    /**
     * @param array<string, mixed> $data
     */
    public function run(array $data): Sprint
    {
        $data = $this->normalizeDoneListIds($data);

        return Sprint::create($data);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalizeDoneListIds(array $data): array
    {
        if (array_key_exists('done_list_ids', $data) && is_string($data['done_list_ids'])) {
            $decoded = json_decode($data['done_list_ids'], true);
            $data['done_list_ids'] = $decoded === null ? null : $decoded;
        }

        return $data;
    }
}
