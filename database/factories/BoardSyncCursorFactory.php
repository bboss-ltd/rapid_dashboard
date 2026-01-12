<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class BoardSyncCursorFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'trello_board_id' => fake()->word(),
            'last_action_occurred_at' => fake()->dateTime(),
            'last_action_id' => fake()->word(),
            'last_polled_at' => fake()->dateTime(),
        ];
    }
}
