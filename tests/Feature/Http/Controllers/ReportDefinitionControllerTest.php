<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\ReportDefinition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * @see \App\Http\Controllers\ReportDefinitionController
 */
final class ReportDefinitionControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function index_displays_view(): void
    {
        $reportDefinitions = ReportDefinition::factory()->count(3)->create();

        $response = $this->get(route('report-definitions.index'));

        $response->assertOk();
        $response->assertViewIs('reportDefinition.index');
        $response->assertViewHas('reportDefinitions', $reportDefinitions);
    }


    #[Test]
    public function show_displays_view(): void
    {
        $reportDefinition = ReportDefinition::factory()->create();

        $response = $this->get(route('report-definitions.show', $reportDefinition));

        $response->assertOk();
        $response->assertViewIs('reportDefinition.show');
        $response->assertViewHas('reportDefinition', $reportDefinition);
    }
}
