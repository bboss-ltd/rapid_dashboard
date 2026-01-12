<?php

namespace Database\Factories;

use App\Models\ReportDefinition;
use App\Models\Sprint;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReportRunFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'report_definition_id' => ReportDefinition::factory(),
            'sprint_id' => Sprint::factory(),
            'status' => fake()->randomElement(["queued","running","success","failed"]),
            'params' => '{}',
            'snapshot_ref' => '{}',
            'output_format' => fake()->word(),
            'output_path' => fake()->word(),
            'started_at' => fake()->dateTime(),
            'finished_at' => fake()->dateTime(),
            'error_message' => fake()->text(),
            'requested_by_user_id' => User::factory(),
            'user_id' => User::factory(),
        ];
    }
}
