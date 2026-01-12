<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Sprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Carbon;
use JMac\Testing\Traits\AdditionalAssertions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * @see \App\Http\Controllers\SprintController
 */
final class SprintControllerTest extends TestCase
{
    use AdditionalAssertions, RefreshDatabase, WithFaker;

    #[Test]
    public function index_displays_view(): void
    {
        $sprints = Sprint::factory()->count(3)->create();

        $response = $this->get(route('sprints.index'));

        $response->assertOk();
        $response->assertViewIs('sprint.index');
        $response->assertViewHas('sprints', $sprints);
    }


    #[Test]
    public function create_displays_view(): void
    {
        $response = $this->get(route('sprints.create'));

        $response->assertOk();
        $response->assertViewIs('sprint.create');
    }


    #[Test]
    public function store_uses_form_request_validation(): void
    {
        $this->assertActionUsesFormRequest(
            \App\Http\Controllers\SprintController::class,
            'store',
            \App\Http\Requests\SprintStoreRequest::class
        );
    }

    #[Test]
    public function store_saves_and_redirects(): void
    {
        $name = fake()->name();
        $starts_at = Carbon::parse(fake()->dateTime());
        $ends_at = Carbon::parse(fake()->dateTime());

        $response = $this->post(route('sprints.store'), [
            'name' => $name,
            'starts_at' => $starts_at->toDateTimeString(),
            'ends_at' => $ends_at->toDateTimeString(),
        ]);

        $sprints = Sprint::query()
            ->where('name', $name)
            ->where('starts_at', $starts_at)
            ->where('ends_at', $ends_at)
            ->get();
        $this->assertCount(1, $sprints);
        $sprint = $sprints->first();

        $response->assertRedirect(route('sprints.index'));
        $response->assertSessionHas('sprint.id', $sprint->id);
    }


    #[Test]
    public function show_displays_view(): void
    {
        $sprint = Sprint::factory()->create();

        $response = $this->get(route('sprints.show', $sprint));

        $response->assertOk();
        $response->assertViewIs('sprint.show');
        $response->assertViewHas('sprint', $sprint);
    }


    #[Test]
    public function edit_displays_view(): void
    {
        $sprint = Sprint::factory()->create();

        $response = $this->get(route('sprints.edit', $sprint));

        $response->assertOk();
        $response->assertViewIs('sprint.edit');
        $response->assertViewHas('sprint', $sprint);
    }


    #[Test]
    public function update_uses_form_request_validation(): void
    {
        $this->assertActionUsesFormRequest(
            \App\Http\Controllers\SprintController::class,
            'update',
            \App\Http\Requests\SprintUpdateRequest::class
        );
    }

    #[Test]
    public function update_redirects(): void
    {
        $sprint = Sprint::factory()->create();
        $name = fake()->name();
        $starts_at = Carbon::parse(fake()->dateTime());
        $ends_at = Carbon::parse(fake()->dateTime());

        $response = $this->put(route('sprints.update', $sprint), [
            'name' => $name,
            'starts_at' => $starts_at->toDateTimeString(),
            'ends_at' => $ends_at->toDateTimeString(),
        ]);

        $sprint->refresh();

        $response->assertRedirect(route('sprints.index'));
        $response->assertSessionHas('sprint.id', $sprint->id);

        $this->assertEquals($name, $sprint->name);
        $this->assertEquals($starts_at, $sprint->starts_at);
        $this->assertEquals($ends_at, $sprint->ends_at);
    }


    #[Test]
    public function destroy_deletes_and_redirects(): void
    {
        $sprint = Sprint::factory()->create();

        $response = $this->delete(route('sprints.destroy', $sprint));

        $response->assertRedirect(route('sprints.index'));

        $this->assertModelMissing($sprint);
    }
}
