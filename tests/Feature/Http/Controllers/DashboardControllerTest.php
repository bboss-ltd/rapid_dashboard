<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Sprint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * @see \App\Http\Controllers\DashboardController
 */
final class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function index_displays_view(): void
    {
        $sprints = Sprint::factory()->count(3)->create();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboards.index'));

        $response->assertOk();
        $response->assertViewIs('dashboard.index');
        $response->assertViewHas('sprints');
    }


    #[Test]
    public function show_displays_view(): void
    {
        $dashboard = Sprint::factory()->create();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboards.show', $dashboard));

        $response->assertOk();
        $response->assertViewIs('dashboard.show');
        $response->assertViewHas('sprint');
    }
}
