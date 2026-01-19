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
            'type' => $this->faker->randomElement(["start","end","ad_hoc"]),
            'taken_at' => $this->faker->dateTime(),
            'source' => $this->faker->word(),
            'meta' => '{}',
        ];
    }
}
