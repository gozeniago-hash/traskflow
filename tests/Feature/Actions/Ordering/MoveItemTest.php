<?php

namespace Tests\Feature\Actions\Ordering;

use App\Actions\Ordering\MoveItem;
use App\Models\Project;
use App\Models\Section;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Moving an item of an ordered list one place up or down. */
class MoveItemTest extends TestCase
{
    use RefreshDatabase;

    public function test_moving_down_swaps_the_item_with_the_next_one(): void
    {
        $project = Project::factory()->create();
        [$a, $b, $c] = [Section::factory()->for($project)->create(), Section::factory()->for($project)->create(), Section::factory()->for($project)->create()];

        app(MoveItem::class)->handle($project->sections(), $a->id, 1);

        $this->assertSame([$b->id, $a->id, $c->id], $project->sections()->pluck('id')->all());
    }

    public function test_moving_up_swaps_the_item_with_the_previous_one(): void
    {
        $project = Project::factory()->create();
        [$a, $b, $c] = [Section::factory()->for($project)->create(), Section::factory()->for($project)->create(), Section::factory()->for($project)->create()];

        app(MoveItem::class)->handle($project->sections(), $c->id, -1);

        $this->assertSame([$a->id, $c->id, $b->id], $project->sections()->pluck('id')->all());
    }

    public function test_moving_the_first_item_up_changes_nothing(): void
    {
        $project = Project::factory()->create();
        [$a, $b] = [Section::factory()->for($project)->create(), Section::factory()->for($project)->create()];

        app(MoveItem::class)->handle($project->sections(), $a->id, -1);

        $this->assertSame([$a->id, $b->id], $project->sections()->pluck('id')->all());
        $this->assertSame([1, 2], $project->sections()->pluck('position')->all());
    }

    public function test_an_id_outside_the_list_changes_nothing(): void
    {
        $project = Project::factory()->create();
        [$a, $b] = [Section::factory()->for($project)->create(), Section::factory()->for($project)->create()];
        $other = Section::factory()->create();

        app(MoveItem::class)->handle($project->sections(), $other->id, 1);

        $this->assertSame([$a->id, $b->id], $project->sections()->pluck('id')->all());
    }
}
