<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class CardFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'trello_card_id' => fake()->word(),
            'name' => fake()->name(),
            'last_activity_at' => fake()->dateTime(),
        ];
    }
}
