<?php

namespace App\Domains\Estimation;

class EstimatePointsResolver
{
    /**
     * Map Trello dropdown label -> points.
     * For now, use label matching. Later, you can switch to enum/rank without changing callers.
     */
    public function pointsForLabel(?string $label): ?int
    {
        if (!$label) return null;

        $matrix = config('estimation');

        // If your config uses string keys:
        if (isset($matrix[$label])) {
            return (int) $matrix[$label];
        }

        // If you later switch config to rank keys, you can adapt here.
        return null;
    }
}
