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
            'trello_action_id' => $this->faker->word(),
            'trello_board_id' => $this->faker->word(),
            'trello_card_id' => $this->faker->word(),
            'type' => $this->faker->word(),
            'occurred_at' => $this->faker->dateTime(),
            'payload' => '{}',
            'processed_at' => $this->faker->dateTime(),
        ];
    }
}
