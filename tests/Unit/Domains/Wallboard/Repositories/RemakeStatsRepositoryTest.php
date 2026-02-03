<?php

namespace Tests\Unit\Domains\Wallboard\Repositories;

use App\Domains\Wallboard\Repositories\RemakeStatsRepository;
use App\Models\Card;
use App\Models\Sprint;
use App\Models\SprintRemake;
use App\Models\SprintSnapshot;
use App\Models\SprintSnapshotCard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RemakeStatsRepositoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('trello_sync.remake_reason_labels', ['Reason A']);
        config()->set('trello_sync.remake_label_actions.remove', []);
    }

    public function test_build_remake_reason_stats_can_ignore_sprint(): void
    {
        $repo = new RemakeStatsRepository();

        $sprintA = Sprint::factory()->create();
        $sprintB = Sprint::factory()->create();

        $day = Carbon::create(2026, 1, 30, 9, 0, 0);

        SprintRemake::create([
            'sprint_id' => $sprintA->id,
            'trello_card_id' => 'card-a1',
            'label_name' => 'rm: label',
            'reason_label' => 'Reason A',
            'first_seen_at' => $day->copy(),
            'last_seen_at' => $day->copy()->addMinutes(5),
        ]);

        SprintRemake::create([
            'sprint_id' => $sprintB->id,
            'trello_card_id' => 'card-b1',
            'label_name' => 'rm: label',
            'reason_label' => 'Reason A',
            'first_seen_at' => $day->copy()->addHour(),
            'last_seen_at' => $day->copy()->addHour()->addMinutes(5),
        ]);

        [$start, $end] = [$day->copy()->startOfDay(), $day->copy()->endOfDay()];

        $onlySprint = $repo->buildRemakeReasonStats($sprintA, $start, $end, false);
        $allSprints = $repo->buildRemakeReasonStats($sprintA, $start, $end, true);

        $this->assertSame(1, $onlySprint['counts']['Reason A']);
        $this->assertSame(2, $allSprints['counts']['Reason A']);
    }

    public function test_build_remake_stats_date_mode_ignores_sprint(): void
    {
        $repo = new RemakeStatsRepository();

        $sprintA = Sprint::factory()->create();
        $sprintB = Sprint::factory()->create();

        $day = Carbon::create(2026, 1, 30, 10, 0, 0);

        SprintRemake::create([
            'sprint_id' => $sprintA->id,
            'trello_card_id' => 'card-a2',
            'label_name' => 'rm: label',
            'reason_label' => 'Reason A',
            'first_seen_at' => $day->copy(),
            'last_seen_at' => $day->copy()->addMinutes(5),
        ]);

        SprintRemake::create([
            'sprint_id' => $sprintB->id,
            'trello_card_id' => 'card-b2',
            'label_name' => 'rm: label',
            'reason_label' => 'Reason A',
            'first_seen_at' => $day->copy()->addMinutes(30),
            'last_seen_at' => $day->copy()->addMinutes(35),
        ]);

        $stats = $repo->buildRemakeStats($sprintA, ['ad_hoc'], $day->copy(), true);

        $this->assertSame(2, $stats['today']);
        $this->assertSame(2, $stats['total']);
        $this->assertSame(2, $stats['requested_today']);
        $this->assertSame(2, $stats['accepted_today']);
        $this->assertSame(0, $stats['prev_today']);
    }

    public function test_build_remake_stats_excludes_remove_labels_from_current_list(): void
    {
        config()->set('trello_sync.remake_label_actions.remove', ['test' => 0]);

        $repo = new RemakeStatsRepository();

        $sprint = Sprint::factory()->create([
            'remakes_list_id' => 'list-remakes',
        ]);

        $snapshot = SprintSnapshot::factory()->create([
            'sprint_id' => $sprint->id,
            'type' => 'ad_hoc',
            'taken_at' => now(),
        ]);

        $cardKeep = Card::factory()->create(['trello_card_id' => 'card-keep']);
        $cardRemove = Card::factory()->create(['trello_card_id' => 'card-remove']);

        SprintSnapshotCard::factory()->create([
            'sprint_snapshot_id' => $snapshot->id,
            'card_id' => $cardKeep->id,
            'trello_list_id' => 'list-remakes',
        ]);

        SprintSnapshotCard::factory()->create([
            'sprint_snapshot_id' => $snapshot->id,
            'card_id' => $cardRemove->id,
            'trello_list_id' => 'list-remakes',
        ]);

        SprintRemake::create([
            'sprint_id' => $sprint->id,
            'trello_card_id' => 'card-keep',
            'label_name' => 'punch',
            'reason_label' => 'Reason A',
            'first_seen_at' => now()->subMinute(),
            'last_seen_at' => now(),
        ]);

        SprintRemake::create([
            'sprint_id' => $sprint->id,
            'trello_card_id' => 'card-remove',
            'label_name' => 'Test',
            'first_seen_at' => now()->subMinute(),
            'last_seen_at' => now(),
        ]);

        $stats = $repo->buildRemakeStats($sprint, ['ad_hoc']);

        $this->assertSame(1, $stats['total']);
    }
}
