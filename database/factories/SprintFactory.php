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
            'name' => $this->faker->name(),
            'trello_board_id' => $this->faker->word(),
            'starts_at' => $this->faker->dateTime(),
            'ends_at' => $this->faker->dateTime(),
            'closed_at' => $this->faker->dateTime(),
            'done_list_ids' => '{}',
            'trello_control_card_id' => $this->faker->word(),
            'trello_status_custom_field_id' => $this->faker->word(),
            'trello_closed_option_id' => $this->faker->word(),
            'last_polled_at' => $this->faker->dateTime(),
        ];
    }
}
