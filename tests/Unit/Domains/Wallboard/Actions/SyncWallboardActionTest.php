<?php

namespace Tests\Unit\Domains\Wallboard\Actions;

use App\Domains\Wallboard\Actions\SyncWallboardAction;
use App\Domains\Wallboard\Events\WallboardSynced;
use App\Models\Sprint;
use App\Services\Trello\TrelloClient;
use App\Services\Trello\TrelloSprintBoardReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SyncWallboardActionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_syncs_and_dispatches_event(): void
    {
        Event::fake();

        $sprint = Sprint::factory()->create([
            'trello_board_id' => 'board_123',
            'done_list_ids' => [],
            'closed_at' => null,
        ]);

        $this->mock(TrelloClient::class)
            ->shouldReceive('get')
            ->andReturn([]);

        $this->mock(TrelloSprintBoardReader::class)
            ->shouldReceive('fetchCustomFields')
            ->andReturn([])
            ->shouldReceive('buildDropdownLookup')
            ->andReturn([])
            ->shouldReceive('findCustomFieldIdByName')
            ->andReturn(null)
            ->shouldReceive('fetchCards')
            ->andReturn([]);

        $result = app(SyncWallboardAction::class)->run($sprint);

        $this->assertSame('ad_hoc', $result->snapshot->type);
        $this->assertSame('wallboard', $result->snapshot->source);
        $this->assertSame($sprint->id, $result->snapshot->sprint_id);
        $this->assertNotNull($result->reconcileSnapshot);

        Event::assertDispatched(WallboardSynced::class, function (WallboardSynced $event) use ($sprint, $result) {
            return $event->sprint->is($sprint)
                && $event->snapshot->is($result->snapshot)
                && $event->reconcileSnapshot?->is($result->reconcileSnapshot);
        });
    }
}
