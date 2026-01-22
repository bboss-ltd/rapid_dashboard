<?php

namespace App\Domains\Sprints\Actions;

use App\Models\Sprint;
use App\Models\SprintRemake;
use Illuminate\Support\Carbon;

final class TrackRemakeCardsAction
{
    /**
     * @param array<int, array{trello_card_id:string, card_id:int|null, estimate_points:int|null, reason_label:string|null, reason_label_color:string|null}> $remakes
     */
    public function run(Sprint $sprint, array $remakes, ?Carbon $seenAt = null): void
    {
        if (!$sprint->remakes_list_id) {
            return;
        }

        $timestamp = $seenAt ?? now();

        foreach ($remakes as $remake) {
            $trelloCardId = $remake['trello_card_id'] ?? null;
            if (!$trelloCardId) {
                continue;
            }

            $record = SprintRemake::firstOrCreate(
                [
                    'sprint_id' => $sprint->id,
                    'trello_card_id' => $trelloCardId,
                ],
                [
                    'card_id' => $remake['card_id'] ?? null,
                    'estimate_points' => $remake['estimate_points'] ?? null,
                    'first_seen_at' => $timestamp,
                    'last_seen_at' => $timestamp,
                    'removed_at' => null,
                ]
            );

            $record->update([
                'card_id' => $record->card_id ?: ($remake['card_id'] ?? null),
                'estimate_points' => $remake['estimate_points'] ?? $record->estimate_points,
                'last_seen_at' => $timestamp,
                'removed_at' => $record->removed_at,
            ]);

            $reasonLabel = trim((string) ($remake['reason_label'] ?? ''));
            $reasonColor = trim((string) ($remake['reason_label_color'] ?? ''));
            if ($reasonLabel !== '') {
                $record->update([
                    'reason_label' => $reasonLabel,
                    'reason_label_color' => $reasonColor !== '' ? $reasonColor : $record->reason_label_color,
                    'reason_set_at' => $timestamp,
                ]);
            }
        }
    }
}
