<?php

namespace Database\Factories;

use App\Models\Sprint;
use Illuminate\Database\Eloquent\Factories\Factory;

class SprintCloseSnapshotFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'sprint_id' => Sprint::factory(),
            'closed_at' => fake()->dateTime(),
            'committed_points' => fake()->numberBetween(-10000, 10000),
            'completed_points' => fake()->numberBetween(-10000, 10000),
            'scope_points' => fake()->numberBetween(-10000, 10000),
            'committed_card_ids' => '{}',
            'completed_card_ids' => '{}',
            'scope_card_ids' => '{}',
            'meta' => '{}',
        ];
    }
}
