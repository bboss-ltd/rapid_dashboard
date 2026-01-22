<?php

namespace App\Domains\Sprints\Actions;

use App\Models\Sprint;

final class UpdateSprintAction
{
    /**
     * @param array<string, mixed> $data
     */
    public function run(Sprint $sprint, array $data): Sprint
    {
        $data = $this->normalizeDoneListIds($data);

        $sprint->update($data);

        return $sprint;
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
