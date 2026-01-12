<?php

namespace Database\Seeders;

use App\Models\Card;
use App\Models\Sprint;
use App\Models\SprintSnapshot;
use App\Models\SprintSnapshotCard;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class DemoDashboardSeeder extends Seeder
{
    public function run(): void
    {
        // Wipe domain tables
        SprintSnapshotCard::query()->delete();
        SprintSnapshot::query()->delete();
        Card::query()->delete();
        Sprint::query()->delete();

        $now = now();

        // Demo list ids
        $todoList = 'LIST_TODO_DEMO';
        $doingList = 'LIST_DOING_DEMO';
        $doneList = 'LIST_DONE_DEMO';

        // Schema feature flags (so this seeder matches YOUR migrations)
        $sprintsHasNotes = Schema::hasColumn('sprints', 'notes');
        $cardsHasEstimatePoints = Schema::hasColumn('cards', 'estimate_points');
        $cardsHasTrelloListId = Schema::hasColumn('cards', 'trello_list_id');

        // Create 6 sprints; last stays open
        $sprints = collect(range(0, 5))->map(function (int $i) use ($now, $doneList, $sprintsHasNotes) {
            $starts = $now->copy()->subWeeks(10)->addWeeks($i * 2)->startOfDay()->addHours(9);
            $ends = $starts->copy()->addDays(13)->setTime(17, 0);

            $payload = [
                'name' => "Sprint " . ($i + 1),
                'starts_at' => $starts,
                'ends_at' => $ends,
                'closed_at' => $i < 5 ? $ends->copy()->addHours(1) : null,
                'trello_board_id' => 'DEMO_BOARD_' . ($i + 1),
                'done_list_ids' => [$doneList],
            ];

            if ($sprintsHasNotes) {
                $payload['notes'] = 'Seeded demo sprint for UI testing.';
            }

            return Sprint::create($payload);
        });

        foreach ($sprints as $sprint) {
            $isClosed = $sprint->closed_at !== null;

            // Create cards
            $cards = collect(range(1, 28))->map(function ($n) use ($todoList, $cardsHasEstimatePoints, $cardsHasTrelloListId) {
                $payload = [
                    'trello_card_id' => (string) Str::uuid(),
                    'name' => "Demo Card {$n}",
                    'last_activity_at' => now()->subDays(rand(0, 30)),
                ];

                // If your cards table has trello_list_id, seed it
                if ($cardsHasTrelloListId) {
                    $payload['trello_list_id'] = $todoList;
                }

                // If your cards table has estimate_points, seed it (yours doesn't currently)
                if ($cardsHasEstimatePoints) {
                    $payload['estimate_points'] = null;
                }

                return Card::create($payload);
            });

            // Baseline points used only for snapshot_cards (that table DOES have estimate_points)
            $pointsOptions = [1, 2, 3, 5, 8, 13];
            $baseline = $cards->mapWithKeys(function (Card $c) use ($pointsOptions) {
                return [$c->id => $pointsOptions[array_rand($pointsOptions)]];
            });

            $startAt = Carbon::parse($sprint->starts_at)->copy()->addHours(1);
            $endAt = Carbon::parse($sprint->ends_at)->copy()->subHours(1);

            $startSnap = SprintSnapshot::create([
                'sprint_id' => $sprint->id,
                'type' => 'start',
                'taken_at' => $startAt,
                'source' => 'seed',
                'meta' => ['seed' => true],
            ]);

            $adHocSnaps = collect(range(1, 6))->map(function (int $k) use ($sprint, $startAt, $endAt) {
                $t = $startAt->copy()->addDays($k * 2)->setTime(16, 0);
                if ($t->greaterThan($endAt)) $t = $endAt->copy()->subHours(4);

                return SprintSnapshot::create([
                    'sprint_id' => $sprint->id,
                    'type' => 'ad_hoc',
                    'taken_at' => $t,
                    'source' => 'seed',
                    'meta' => ['seed' => true, 'point' => $k],
                ]);
            });

            $endSnap = null;
            if ($isClosed) {
                $endSnap = SprintSnapshot::create([
                    'sprint_id' => $sprint->id,
                    'type' => 'end',
                    'taken_at' => $endAt,
                    'source' => 'seed',
                    'meta' => ['seed' => true],
                ]);
            }

            $writeSnapshotCards = function (SprintSnapshot $snap, float $completionRatio) use (
                $cards, $baseline, $todoList, $doingList, $doneList
            ) {
                $total = $cards->count();
                $doneCount = (int) floor($total * $completionRatio);
                $doneIds = $cards->take($doneCount)->pluck('id')->all();

                foreach ($cards as $i => $card) {
                    $basePoints = (int) $baseline[$card->id];

                    // simulate estimate changes (10% of rows)
                    $points = $basePoints;
                    if (rand(1, 100) <= 10) {
                        $deltas = [-1, +1, +2];
                        $points = max(1, $basePoints + $deltas[array_rand($deltas)]);
                    }

                    $isDone = in_array($card->id, $doneIds, true);

                    $listId = $isDone
                        ? $doneList
                        : ($i < ($total * 0.35) ? $doingList : $todoList);

                    SprintSnapshotCard::create([
                        'sprint_snapshot_id' => $snap->id,
                        'card_id' => $card->id,
                        'trello_list_id' => $listId,
                        'estimate_points' => $points,
                        'is_done' => $isDone,
                        'meta' => null,
                    ]);
                }
            };

            $writeSnapshotCards($startSnap, 0.0);

            foreach ($adHocSnaps as $k => $snap) {
                $ratio = min(0.95, 0.15 + ($k * 0.13));
                $writeSnapshotCards($snap, $ratio);
            }

            if ($endSnap) {
                $finalRatio = rand(75, 100) / 100;
                $writeSnapshotCards($endSnap, $finalRatio);
            }
        }
    }
}
