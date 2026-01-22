<?php

namespace Database\Factories;

use App\Models\ReportDefinition;
use App\Models\ReportRun;
use App\Models\Sprint;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReportRunFactory extends Factory
{
    protected $model = ReportRun::class;

    public function definition(): array
    {
        return [
            'report_definition_id' => ReportDefinition::factory(),
            'sprint_id' => Sprint::factory(),
            'status' => $this->faker->randomElement(['queued', 'running', 'success', 'failed']),
            'params' => ['source' => 'factory'],
            'snapshot_ref' => null,
            'output_format' => null,
            'output_path' => null,
            'started_at' => null,
            'finished_at' => null,
            'error_message' => null,
            'requested_by_user_id' => User::factory(),
            'user_id' => User::factory(),
        ];
    }
}
