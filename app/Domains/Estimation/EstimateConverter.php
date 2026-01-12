<?php

namespace App\Domains\Estimation;

class EstimateConverter
{
    public function __construct(private array $matrix) {}

    public function toPoints(?string $estimationText): ?int
    {
        if (!$estimationText) return null;
        return $this->matrix[$estimationText] ?? null;
    }
}
