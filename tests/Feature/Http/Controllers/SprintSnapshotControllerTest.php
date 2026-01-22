<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Sprint;
use App\Models\SprintSnapshot;
use App\Models\User;
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
        $sprint = Sprint::factory()->create();
        $sprintSnapshots = SprintSnapshot::factory()->count(3)->create([
            'sprint_id' => $sprint->id,
        ]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('sprints.snapshots.index', $sprint));

        $response->assertOk();
        $response->assertViewIs('sprintSnapshot.index');
        $response->assertViewHas('snapshots');
    }


    #[Test]
    public function show_displays_view(): void
    {
        $sprint = Sprint::factory()->create();
        $sprintSnapshot = SprintSnapshot::factory()->create([
            'sprint_id' => $sprint->id,
        ]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('sprints.snapshots.show', [$sprint, $sprintSnapshot]));

        $response->assertOk();
        $response->assertViewIs('sprintSnapshot.show');
        $response->assertViewHas('snapshot', $sprintSnapshot);
    }
}
