<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\ReportDefinition;
use App\Models\ReportSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use JMac\Testing\Traits\AdditionalAssertions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * @see \App\Http\Controllers\ReportScheduleController
 */
final class ReportScheduleControllerTest extends TestCase
{
    use AdditionalAssertions, RefreshDatabase, WithFaker;

    #[Test]
    public function index_displays_view(): void
    {
        $reportSchedules = ReportSchedule::factory()->count(3)->create();

        $response = $this->get(route('report-schedules.index'));

        $response->assertOk();
        $response->assertViewIs('reportSchedule.index');
        $response->assertViewHas('reportSchedules', $reportSchedules);
    }


    #[Test]
    public function create_displays_view(): void
    {
        $response = $this->get(route('report-schedules.create'));

        $response->assertOk();
        $response->assertViewIs('reportSchedule.create');
    }


    #[Test]
    public function store_uses_form_request_validation(): void
    {
        $this->assertActionUsesFormRequest(
            \App\Http\Controllers\ReportScheduleController::class,
            'store',
            \App\Http\Requests\ReportScheduleStoreRequest::class
        );
    }

    #[Test]
    public function store_saves_and_redirects(): void
    {
        $report_definition = ReportDefinition::factory()->create();
        $name = fake()->name();
        $is_enabled = fake()->boolean();
        $cron = fake()->word();
        $timezone = fake()->word();
        $default_params = fake()->;

        $response = $this->post(route('report-schedules.store'), [
            'report_definition_id' => $report_definition->id,
            'name' => $name,
            'is_enabled' => $is_enabled,
            'cron' => $cron,
            'timezone' => $timezone,
            'default_params' => $default_params,
        ]);

        $reportSchedules = ReportSchedule::query()
            ->where('report_definition_id', $report_definition->id)
            ->where('name', $name)
            ->where('is_enabled', $is_enabled)
            ->where('cron', $cron)
            ->where('timezone', $timezone)
            ->where('default_params', $default_params)
            ->get();
        $this->assertCount(1, $reportSchedules);
        $reportSchedule = $reportSchedules->first();

        $response->assertRedirect(route('reportSchedules.index'));
        $response->assertSessionHas('reportSchedule.id', $reportSchedule->id);
    }


    #[Test]
    public function show_displays_view(): void
    {
        $reportSchedule = ReportSchedule::factory()->create();

        $response = $this->get(route('report-schedules.show', $reportSchedule));

        $response->assertOk();
        $response->assertViewIs('reportSchedule.show');
        $response->assertViewHas('reportSchedule', $reportSchedule);
    }


    #[Test]
    public function edit_displays_view(): void
    {
        $reportSchedule = ReportSchedule::factory()->create();

        $response = $this->get(route('report-schedules.edit', $reportSchedule));

        $response->assertOk();
        $response->assertViewIs('reportSchedule.edit');
        $response->assertViewHas('reportSchedule', $reportSchedule);
    }


    #[Test]
    public function update_uses_form_request_validation(): void
    {
        $this->assertActionUsesFormRequest(
            \App\Http\Controllers\ReportScheduleController::class,
            'update',
            \App\Http\Requests\ReportScheduleUpdateRequest::class
        );
    }

    #[Test]
    public function update_redirects(): void
    {
        $reportSchedule = ReportSchedule::factory()->create();
        $report_definition = ReportDefinition::factory()->create();
        $name = fake()->name();
        $is_enabled = fake()->boolean();
        $cron = fake()->word();
        $timezone = fake()->word();
        $default_params = fake()->;

        $response = $this->put(route('report-schedules.update', $reportSchedule), [
            'report_definition_id' => $report_definition->id,
            'name' => $name,
            'is_enabled' => $is_enabled,
            'cron' => $cron,
            'timezone' => $timezone,
            'default_params' => $default_params,
        ]);

        $reportSchedule->refresh();

        $response->assertRedirect(route('reportSchedules.index'));
        $response->assertSessionHas('reportSchedule.id', $reportSchedule->id);

        $this->assertEquals($report_definition->id, $reportSchedule->report_definition_id);
        $this->assertEquals($name, $reportSchedule->name);
        $this->assertEquals($is_enabled, $reportSchedule->is_enabled);
        $this->assertEquals($cron, $reportSchedule->cron);
        $this->assertEquals($timezone, $reportSchedule->timezone);
        $this->assertEquals($default_params, $reportSchedule->default_params);
    }


    #[Test]
    public function destroy_deletes_and_redirects(): void
    {
        $reportSchedule = ReportSchedule::factory()->create();

        $response = $this->delete(route('report-schedules.destroy', $reportSchedule));

        $response->assertRedirect(route('reportSchedules.index'));

        $this->assertModelMissing($reportSchedule);
    }
}
