<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Rearranging sections: project managers and the project's owner. */
class SectionOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DemoSeeder::class);
    }

    protected function user(string $email): User
    {
        return User::where('email', $email)->first();
    }

    protected function web(): Project
    {
        return Project::where('name', 'Brightside Website Redesign')->first();
    }

    protected function order(Project $p): array
    {
        return $p->sections()->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    public function test_owner_who_is_a_regular_member_can_rearrange_sections(): void
    {
        $leo = $this->user('leo@taskflow.test'); // workspace role: member
        $web = $this->web();
        $web->update(['owner_id' => $leo->id]);
        $this->actingAs($leo);
        $this->assertFalse($leo->can('update', $web), 'still no access to project settings');

        $reversed = array_reverse($this->order($web));
        foreach ([\App\Livewire\Projects\ListView::class, \App\Livewire\Projects\BoardView::class] as $view) {
            Livewire::test($view, ['project' => $web])
                ->assertSeeHtml('data-section-grip')
                ->call('reorderSections', $reversed)->assertOk();
            $this->assertSame($reversed, $this->order($web));
            $reversed = array_reverse($reversed);
        }

        // The menu's Move up / Move down works for them too.
        $first = $this->order($web)[0];
        Livewire::test(\App\Livewire\Projects\ListView::class, ['project' => $web])->call('moveSection', $first, 1);
        $this->assertSame($first, $this->order($web)[1]);
    }

    public function test_other_members_cannot_rearrange_sections(): void
    {
        $leo = $this->user('leo@taskflow.test'); // member, not the owner of Brightside
        $this->actingAs($leo);
        $web = $this->web();
        $before = $this->order($web);

        Livewire::test(\App\Livewire\Projects\ListView::class, ['project' => $web])
            ->assertDontSeeHtml('data-section-grip')
            ->call('reorderSections', array_reverse($before))->assertForbidden();
        Livewire::test(\App\Livewire\Projects\BoardView::class, ['project' => $web])
            ->call('moveSection', $before[0], 1)->assertForbidden();

        $this->assertSame($before, $this->order($web));
    }

    public function test_reorder_ignores_ids_from_elsewhere_or_a_stale_page(): void
    {
        $this->actingAs($this->user('admin@taskflow.test'));
        $web = $this->web();
        $before = $this->order($web);
        $other = Project::where('name', 'Q4 Marketing Campaign')->first()->sections()->value('id');

        $list = Livewire::test(\App\Livewire\Projects\ListView::class, ['project' => $web]);
        $list->call('reorderSections', [...array_slice($before, 1), $other]);   // foreign section
        $list->call('reorderSections', array_slice(array_reverse($before), 1)); // one missing
        $this->assertSame($before, $this->order($web));
        $this->assertSame(1, (int) \App\Models\Section::find($other)->position, 'other project untouched');
    }
}
