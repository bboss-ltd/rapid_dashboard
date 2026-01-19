<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\ReportDefinition;
use App\Models\ReportRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use JMac\Testing\Traits\AdditionalAssertions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * @see \App\Http\Controllers\ReportRunController
 */
final class ReportRunControllerTest extends TestCase
{
    use AdditionalAssertions, RefreshDatabase, WithFaker;

    #[Test]
    public function index_displays_view(): void
    {
        $reportRuns = ReportRun::factory()->count(3)->create();

        $response = $this->get(route('report-runs.index'));

        $response->assertOk();
        $response->assertViewIs('report-run.index');
        $response->assertViewHas('reportRuns', $reportRuns);
    }


    #[Test]
    public function create_displays_view(): void
    {
        $response = $this->get(route('report-runs.create'));

        $response->assertOk();
        $response->assertViewIs('report-run.create');
    }


    #[Test]
    public function store_uses_form_request_validation(): void
    {
        $this->assertActionUsesFormRequest(
            \App\Http\Controllers\ReportRunController::class,
            'store',
            \App\Http\Requests\ReportRunStoreRequest::class
        );
    }

    #[Test]
    public function store_saves_and_redirects(): void
    {
        $report_definition = ReportDefinition::factory()->create();
        $status = $this->faker->randomElement(/** enum_attributes **/);
        $params = $this->faker->;
        $user = User::factory()->create();

        $response = $this->post(route('report-runs.store'), [
            'report_definition_id' => $report_definition->id,
            'status' => $status,
            'params' => $params,
            'user_id' => $user->id,
        ]);

        $reportRuns = ReportRun::query()
            ->where('report_definition_id', $report_definition->id)
            ->where('status', $status)
            ->where('params', $params)
            ->where('user_id', $user->id)
            ->get();
        $this->assertCount(1, $reportRuns);
        $reportRun = $reportRuns->first();

        $response->assertRedirect(route('reportRun.index'));
        $response->assertSessionHas('reportRun.id', $reportRun->id);
    }


    #[Test]
    public function show_displays_view(): void
    {
        $reportRun = ReportRun::factory()->create();

        $response = $this->get(route('report-runs.show', $reportRun));

        $response->assertOk();
        $response->assertViewIs('report-run.show');
        $response->assertViewHas('reportRun', $reportRun);
    }
}
