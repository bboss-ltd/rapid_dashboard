<?php

namespace Database\Factories;

use App\Models\Sprint;
use Illuminate\Database\Eloquent\Factories\Factory;

class SprintSnapshotFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'sprint_id' => Sprint::factory(),
            'type' => fake()->randomElement(["start","end","ad_hoc"]),
            'taken_at' => fake()->dateTime(),
            'source' => fake()->word(),
            'meta' => '{}',
        ];
    }
}
