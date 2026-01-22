<?php

namespace Tests\Unit\Domains\Sprints\Repositories;

use App\Domains\Sprints\Repositories\SprintSnapshotRepository;
use App\Models\Sprint;
use App\Models\SprintSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SprintSnapshotRepositoryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_latest_snapshot_by_type(): void
    {
        $sprint = Sprint::factory()->create();

        SprintSnapshot::factory()->create([
            'sprint_id' => $sprint->id,
            'type' => 'ad_hoc',
            'taken_at' => now()->subDay(),
        ]);

        $latest = SprintSnapshot::factory()->create([
            'sprint_id' => $sprint->id,
            'type' => 'ad_hoc',
            'taken_at' => now(),
        ]);

        $repo = app(SprintSnapshotRepository::class);

        $result = $repo->latestByType($sprint, 'ad_hoc');

        $this->assertNotNull($result);
        $this->assertTrue($latest->is($result));
    }

    #[Test]
    public function it_checks_for_snapshot_type(): void
    {
        $sprint = Sprint::factory()->create();

        SprintSnapshot::factory()->create([
            'sprint_id' => $sprint->id,
            'type' => 'end',
        ]);

        $repo = app(SprintSnapshotRepository::class);

        $this->assertTrue($repo->hasSnapshotType($sprint, 'end'));
        $this->assertFalse($repo->hasSnapshotType($sprint, 'start'));
    }
}
