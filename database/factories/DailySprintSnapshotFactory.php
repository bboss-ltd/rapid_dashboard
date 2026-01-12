<?php

namespace Database\Factories;

use App\Models\Sprint;
use Illuminate\Database\Eloquent\Factories\Factory;

class DailySprintSnapshotFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'sprint_id' => Sprint::factory(),
            'snapshot_date' => fake()->date(),
            'remaining_points' => fake()->numberBetween(-10000, 10000),
            'completed_points_to_date' => fake()->numberBetween(-10000, 10000),
            'scope_points' => fake()->numberBetween(-10000, 10000),
            'cards_done_count' => fake()->numberBetween(-10000, 10000),
            'cards_total_count' => fake()->numberBetween(-10000, 10000),
            'meta' => '{}',
        ];
    }
}
