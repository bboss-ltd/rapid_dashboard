<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ReportDefinitionFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'slug' => fake()->slug(),
            'name' => fake()->name(),
            'description' => fake()->text(),
            'param_schema' => '{}',
            'supported_formats' => '{}',
            'is_enabled' => fake()->boolean(),
        ];
    }
}
