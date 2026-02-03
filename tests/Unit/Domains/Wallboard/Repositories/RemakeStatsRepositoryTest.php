<?php

namespace Tests\Unit\Domains\Wallboard\Repositories;

use App\Domains\Wallboard\Repositories\RemakeStatsRepository;
use App\Models\Sprint;
use App\Models\SprintRemake;
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
}
