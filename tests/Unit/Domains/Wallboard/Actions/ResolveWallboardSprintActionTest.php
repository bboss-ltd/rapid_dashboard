<?php

namespace Tests\Unit\Domains\Wallboard\Actions;

use App\Domains\Wallboard\Actions\ResolveWallboardSprintAction;
use App\Models\Sprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ResolveWallboardSprintActionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_redirects_when_single_active_status_exists(): void
    {
        $sprint = Sprint::factory()->create([
            'status' => 'active',
            'closed_at' => null,
        ]);

        $result = app(ResolveWallboardSprintAction::class)->run();

        $this->assertSame('redirect', $result['mode']);
        $this->assertTrue($sprint->is($result['sprint']));
    }

    #[Test]
    public function it_falls_back_to_date_active_when_multiple_status_active(): void
    {
        Sprint::factory()->count(2)->create([
            'status' => 'active',
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(5),
            'closed_at' => null,
        ]);

        $activeByDate = Sprint::factory()->create([
            'status' => 'planned',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'closed_at' => null,
        ]);

        $result = app(ResolveWallboardSprintAction::class)->run();

        $this->assertSame('redirect', $result['mode']);
        $this->assertTrue($activeByDate->is($result['sprint']));
    }

    #[Test]
    public function it_returns_empty_when_no_open_sprint_exists(): void
    {
        Sprint::factory()->create([
            'status' => 'closed',
            'closed_at' => now()->subDay(),
        ]);

        $result = app(ResolveWallboardSprintAction::class)->run();

        $this->assertSame('empty', $result['mode']);
        $this->assertTrue(($result['next'] ?? collect())->isEmpty());
    }
}
