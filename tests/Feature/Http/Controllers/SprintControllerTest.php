<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Sprint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Pagination\LengthAwarePaginator;
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
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('sprints.index'));

        $response->assertOk();
        $response->assertViewIs('sprint.index');
        $response->assertViewHas('sprints');
        $this->assertInstanceOf(LengthAwarePaginator::class, $response->viewData('sprints'));
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
        $name = $this->faker->name();
        $trello_board_id = $this->faker->word();
        $starts_at = Carbon::parse($this->faker->dateTime());
        $ends_at = Carbon::parse($this->faker->dateTime());
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('sprints.store'), [
            'name' => $name,
            'trello_board_id' => $trello_board_id,
            'starts_at' => $starts_at->toDateTimeString(),
            'ends_at' => $ends_at->toDateTimeString(),
        ]);

        $sprints = Sprint::query()
            ->where('name', $name)
            ->where('trello_board_id', $trello_board_id)
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
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('sprints.show', $sprint));

        $response->assertOk();
        $response->assertViewIs('sprint.show');
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
        $name = $this->faker->name();
        $trello_board_id = $this->faker->word();
        $starts_at = Carbon::parse($this->faker->dateTime());
        $ends_at = Carbon::parse($this->faker->dateTime());
        $user = User::factory()->create();

        $response = $this->actingAs($user)->put(route('sprints.update', $sprint), [
            'name' => $name,
            'trello_board_id' => $trello_board_id,
            'starts_at' => $starts_at->toDateTimeString(),
            'ends_at' => $ends_at->toDateTimeString(),
        ]);

        $sprint->refresh();

        $response->assertRedirect(route('sprints.index'));
        $response->assertSessionHas('sprint.id', $sprint->id);

        $this->assertEquals($name, $sprint->name);
        $this->assertEquals($trello_board_id, $sprint->trello_board_id);
        $this->assertEquals($starts_at, $sprint->starts_at);
        $this->assertEquals($ends_at, $sprint->ends_at);
    }
}
