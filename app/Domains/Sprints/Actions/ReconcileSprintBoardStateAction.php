<?php

namespace App\Domains\Sprints\Actions;

use App\Models\Card;
use App\Models\Sprint;
use App\Models\SprintSnapshot;
use App\Models\SprintSnapshotCard;

final class ReconcileSprintBoardStateAction
{
    public function __construct(private FetchSprintBoardStateAction $fetchState) {}

    /**
     * Reconciliation rule:
     * - Compare current Trello board state to the latest ad_hoc snapshot.
     * - If drift is detected, write a new ad_hoc snapshot (source=reconcile) with drift counts in meta.
     * - Never modify old snapshots.
     *
     * @return SprintSnapshot|null  New snapshot if drift detected (or seeded), else null.
     */
    public function run(Sprint $sprint): ?SprintSnapshot
    {
        if ($sprint->isClosed()) {
            return null;
        }

        $latest = $sprint->snapshots()
            ->where('type', 'ad_hoc')
            ->latest('taken_at')
            ->first();

        $state = $this->fetchState->run($sprint);
        $current = collect($state['cards'])->keyBy('trello_card_id');

        // If we have no prior snapshot, seed one as a reconciliation baseline
        if (!$latest) {
            return $this->writeSnapshot($sprint, $state, [
                'reason' => 'no_prior_snapshot',
                'drift' => ['seeded' => true],
            ]);
        }

        // Snapshot map keyed by trello_card_id
        $snapshotRows = $latest->cards()
            ->with('card:id,trello_card_id')
            ->get(['card_id', 'trello_list_id', 'estimate_points', 'is_done']);

        $snap = $snapshotRows->mapWithKeys(function ($r) {
            return [
                $r->card->trello_card_id => [
                    'trello_list_id' => $r->trello_list_id,
                    'estimate_points' => (int) ($r->estimate_points ?? 0),
                    'is_done' => (bool) $r->is_done,
                ],
            ];
        });

        $currentIds = $current->keys();
        $snapIds = $snap->keys();

        $added = $currentIds->diff($snapIds)->values()->all();
        $removed = $snapIds->diff($currentIds)->values()->all();

        $changed_list = 0;
        $changed_points = 0;
        $changed_done = 0;

        foreach ($currentIds->intersect($snapIds) as $id) {
            $c = $current[$id];
            $s = $snap[$id];

            if (($c['trello_list_id'] ?? '') !== ($s['trello_list_id'] ?? '')) {
                $changed_list++;
            }

            $cp = (int) ($c['estimate_points'] ?? 0);
            $sp = (int) ($s['estimate_points'] ?? 0);
            if ($cp !== $sp) {
                $changed_points++;
            }

            if ((bool) $c['is_done'] !== (bool) $s['is_done']) {
                $changed_done++;
            }
        }

        $hasDrift = count($added) || count($removed) || $changed_list || $changed_points || $changed_done;

        if (!$hasDrift) {
            return null;
        }

        return $this->writeSnapshot($sprint, $state, [
            'reason' => 'drift_detected',
            'previous_snapshot_id' => $latest->id,
            'drift' => [
                'added' => count($added),
                'removed' => count($removed),
                'changed_list' => $changed_list,
                'changed_points' => $changed_points,
                'changed_done' => $changed_done,
            ],
        ]);
    }

    private function writeSnapshot(Sprint $sprint, array $state, array $meta): SprintSnapshot
    {
        $snapshot = SprintSnapshot::create([
            'sprint_id' => $sprint->id,
            'type' => 'ad_hoc',
            'taken_at' => $state['taken_at'],
            'source' => 'reconcile',
            'meta' => $meta,
        ]);

        foreach ($state['cards'] as $c) {
            $card = Card::updateOrCreate(
                ['trello_card_id' => $c['trello_card_id']],
                [
                    'name' => $c['name'],
                    'last_activity_at' => $c['last_activity_at'],
                ]
            );

            SprintSnapshotCard::create([
                'sprint_snapshot_id' => $snapshot->id,
                'card_id' => $card->id,
                'trello_list_id' => $c['trello_list_id'],
                'estimate_points' => $c['estimate_points'],
                'is_done' => $c['is_done'],
                'meta' => null,
            ]);
        }

        return $snapshot;
    }
}
