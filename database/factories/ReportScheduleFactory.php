<?php

namespace Database\Factories;

use App\Models\ReportDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReportScheduleFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'report_definition_id' => ReportDefinition::factory(),
            'name' => fake()->name(),
            'is_enabled' => fake()->boolean(),
            'cron' => fake()->word(),
            'timezone' => fake()->word(),
            'default_params' => '{}',
            'last_ran_at' => fake()->dateTime(),
            'next_run_at' => fake()->dateTime(),
        ];
    }
}
