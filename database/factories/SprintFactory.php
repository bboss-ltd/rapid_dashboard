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
            'trello_board_id' => fake()->word(),
            'starts_at' => fake()->dateTime(),
            'ends_at' => fake()->dateTime(),
            'closed_at' => fake()->dateTime(),
            'done_list_ids' => '{}',
            'trello_control_card_id' => fake()->word(),
            'trello_status_custom_field_id' => fake()->word(),
            'trello_closed_option_id' => fake()->word(),
            'last_polled_at' => fake()->dateTime(),
        ];
    }
}
