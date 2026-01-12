<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\SprintSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * @see \App\Http\Controllers\SprintSnapshotController
 */
final class SprintSnapshotControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function index_displays_view(): void
    {
        $sprintSnapshots = SprintSnapshot::factory()->count(3)->create();

        $response = $this->get(route('sprint-snapshots.index'));

        $response->assertOk();
        $response->assertViewIs('sprintSnapshot.index');
        $response->assertViewHas('sprintSnapshots', $sprintSnapshots);
    }


    #[Test]
    public function show_displays_view(): void
    {
        $sprintSnapshot = SprintSnapshot::factory()->create();

        $response = $this->get(route('sprint-snapshots.show', $sprintSnapshot));

        $response->assertOk();
        $response->assertViewIs('sprintSnapshot.show');
        $response->assertViewHas('sprintSnapshot', $sprintSnapshot);
    }
}
