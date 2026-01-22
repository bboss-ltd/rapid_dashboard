<?php

namespace Database\Factories;

use App\Models\ReportDefinition;
use App\Models\ReportSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReportScheduleFactory extends Factory
{
    protected $model = ReportSchedule::class;

    public function definition(): array
    {
        return [
            'report_definition_id' => ReportDefinition::factory(),
            'name' => $this->faker->words(3, true),
            'is_enabled' => $this->faker->boolean(),
            'cron' => '0 6 * * 1-5',
            'timezone' => 'UTC',
            'default_params' => ['source' => 'factory'],
            'last_ran_at' => null,
            'next_run_at' => null,
        ];
    }
}
