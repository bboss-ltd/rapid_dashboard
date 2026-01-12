<?php

namespace Database\Factories;

use App\Models\Card;
use App\Models\SprintSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

class SprintSnapshotCardFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'sprint_snapshot_id' => SprintSnapshot::factory(),
            'card_id' => Card::factory(),
            'trello_list_id' => fake()->word(),
            'estimate_points' => fake()->numberBetween(-10000, 10000),
            'is_done' => fake()->boolean(),
            'meta' => '{}',
        ];
    }
}
