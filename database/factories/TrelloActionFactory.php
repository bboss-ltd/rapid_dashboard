<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class TrelloActionFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'trello_action_id' => fake()->word(),
            'trello_board_id' => fake()->word(),
            'trello_card_id' => fake()->word(),
            'type' => fake()->word(),
            'occurred_at' => fake()->dateTime(),
            'payload' => '{}',
            'processed_at' => fake()->dateTime(),
        ];
    }
}
