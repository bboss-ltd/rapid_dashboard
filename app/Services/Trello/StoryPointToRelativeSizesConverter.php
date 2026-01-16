<?php

namespace App\Services\Trello;

class StoryPointToRelativeSizesConverter
{
    public static function handle(int $storyPoints): string
    {
        if(0 === $storyPoints)
        {
            return 'N/A';
        }

        $stringToValueMap = config('estimation');
        // Sort from highest value to lowest (descending)
        arsort($stringToValueMap);

        $remaining = $storyPoints;
        $result = [];

        foreach ($stringToValueMap as $label => $value) {
            if ($value <= 0) {
                continue; // skip zero or negative values
            }

            $count = intdiv($remaining, $value);

            if ($count > 0) {
                $result[] = "{$count}x {$label}";
                $remaining -= $count * $value;
            }

            if ($remaining === 0) {
                break;
            }
        }

        return $remaining === 0
            ? implode(" + ", $result)
            : "No valid combination found.";
    }
}
