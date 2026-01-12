<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Dashboard;
use App\Models\Sprint;
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
        $dashboards = Dashboard::factory()->count(3)->create();

        $response = $this->get(route('dashboards.index'));

        $response->assertOk();
        $response->assertViewIs('dashboard.index');
        $response->assertViewHas('sprints');
    }


    #[Test]
    public function show_displays_view(): void
    {
        $dashboard = Dashboard::factory()->create();
        $dashboard = Sprint::factory()->create();

        $response = $this->get(route('dashboards.show', $dashboard));

        $response->assertOk();
        $response->assertViewIs('dashboard.show');
        $response->assertViewHas('sprint');
    }
}
