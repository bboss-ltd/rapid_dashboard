<?php

namespace App\Domains\Sprints\Actions;

use App\Models\Sprint;
use App\Models\SprintRemake;
use Illuminate\Support\Carbon;

final class TrackRemakeCardsAction
{
    /**
     * @param array<int, array{
     *   trello_card_id:string,
     *   card_id:int|null,
     *   estimate_points:int|null,
     *   reason_label:string|null,
     *   reason_label_color:string|null,
     *   label_name:string|null,
     *   label_points:int|null,
     *   trello_reason_label:string|null,
     *   production_line:string|null
     * }> $remakes
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

            if (array_key_exists('production_line', $remake)) {
                $productionLine = trim((string) ($remake['production_line'] ?? ''));
                $record->update([
                    'production_line' => $productionLine !== '' ? $productionLine : null,
                ]);
            }

            if (array_key_exists('trello_reason_label', $remake)) {
                $trelloLabel = trim((string) ($remake['trello_reason_label'] ?? ''));
                if ($trelloLabel === '') {
                    $record->update([
                        'trello_reason_label' => null,
                        'trello_reason_set_at' => null,
                    ]);
                } elseif ($record->trello_reason_label !== $trelloLabel) {
                    $record->update([
                        'trello_reason_label' => $trelloLabel,
                        'trello_reason_set_at' => $timestamp,
                    ]);
                }
            }

            if (array_key_exists('label_name', $remake)) {
                $labelName = trim((string) ($remake['label_name'] ?? ''));
                $updates = [];
                if ($labelName === '') {
                    $updates['label_name'] = null;
                    $updates['label_points'] = null;
                    $updates['label_set_at'] = null;
                } else {
                    $updates['label_name'] = $labelName;
                    $updates['label_points'] = $remake['label_points'] ?? $record->label_points;
                    if ($record->label_name !== $labelName) {
                        $updates['label_set_at'] = $timestamp;
                    }
                }
                if ($updates !== []) {
                    $record->update($updates);
                }
            }

            if (array_key_exists('reason_label', $remake)) {
                $reasonLabel = trim((string) ($remake['reason_label'] ?? ''));
                $reasonColor = trim((string) ($remake['reason_label_color'] ?? ''));
                if ($reasonLabel === '') {
                    $record->update([
                        'reason_label' => null,
                        'reason_label_color' => null,
                        'reason_set_at' => null,
                    ]);
                } else {
                    $record->update([
                        'reason_label' => $reasonLabel,
                        'reason_label_color' => $reasonColor !== '' ? $reasonColor : $record->reason_label_color,
                        'reason_set_at' => $record->reason_label !== $reasonLabel ? $timestamp : $record->reason_set_at,
                    ]);
                }
            }
        }
    }
}
