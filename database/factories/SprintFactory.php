<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class SprintFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'starts_at' => fake()->dateTime(),
            'ends_at' => fake()->dateTime(),
            'closed_at' => fake()->dateTime(),
            'trello_board_id' => fake()->word(),
            'done_list_ids' => '{}',
            'notes' => fake()->text(),
        ];
    }
}
