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
            'trello_card_id' => $this->faker->word(),
            'name' => $this->faker->name(),
            'last_activity_at' => $this->faker->dateTime(),
        ];
    }
}
