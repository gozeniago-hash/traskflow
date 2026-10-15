<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Support\NotificationFeed;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NotificationsTest extends TestCase
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

    /** Headlines a person would see, newest first. */
    protected function seen(User $who, ?int $projectId = null): array
    {
        return NotificationFeed::for($who)->latest(100, $projectId)
            ->map(fn (Activity $a) => ($a->user?->name ?? '?').' '.NotificationFeed::for($who)->headline($a))->all();
    }

    public function test_changes_are_recorded_including_deletions(): void
    {
        $leo = $this->user('leo@taskflow.test');
        $web = $this->web();
        $this->actingAs($leo);

        $task = $web->tasks()->whereNull('parent_id')->first();
        $task->update(['title' => 'Homepage hero, final']);
        $section = $web->sections()->create(['name' => 'QA', 'position' => 99]);
        $section->update(['name' => 'Quality checks']);
        $doc = $web->docs()->create(['title' => 'Launch checklist', 'content' => '<p>a</p>', 'created_by' => $leo->id]);
        $doc->update(['content' => '<p>ab</p>']);
        $doc->update(['content' => '<p>abc</p>']); // autosaves become one "edited" entry
        $section->delete();
        Livewire::test(\App\Livewire\TaskPanel::class)->call('open', $task->id)->call('deleteTask');

        $maya = $this->user('maya@taskflow.test');
        $seen = $this->seen($maya, $web->id);
        $this->assertContains('Leo Tan deleted BWR-'.$task->number.' Homepage hero, final', $seen);
        $this->assertContains('Leo Tan deleted the section “Quality checks”', $seen);
        $this->assertContains('Leo Tan renamed the section “QA” to “Quality checks”', $seen);
        $this->assertContains('Leo Tan created the doc “Launch checklist”', $seen);
        $this->assertSame(1, collect($seen)->filter(fn ($s) => str_contains($s, 'edited the doc'))->count());
        // Deleting the task didn't also list each of its files as deleted.
        $this->assertEmpty(collect($seen)->filter(fn ($s) => str_contains($s, 'deleted the file')));
    }

    public function test_people_only_see_projects_they_can_open_and_not_their_own_changes(): void
    {
        $leo = $this->user('leo@taskflow.test');
        $this->actingAs($leo);
        $this->web()->statusUpdates()->create(['user_id' => $leo->id, 'status' => 'on_track', 'title' => 'Sprint 3 done', 'body' => 'x', 'client_visible' => true]);
        Project::where('name', 'Q4 Marketing Campaign')->first()->sections()->create(['name' => 'Paid ads', 'position' => 9]);

        // Maya (Product & Engineering) sees the Brightside update; Jo (Marketing) can't open Brightside.
        $this->assertContains('Leo Tan posted a status update: “Sprint 3 done”', $this->seen($this->user('maya@taskflow.test')));
        $jo = $this->seen($this->user('jo@taskflow.test'));
        $this->assertNotContains('Leo Tan posted a status update: “Sprint 3 done”', $jo);
        $this->assertContains('Leo Tan added the section “Paid ads”', $jo);

        // Leo doesn't get notified about himself (unless he asks to include his own).
        $this->assertEmpty(collect($this->seen($leo))->filter(fn ($s) => str_starts_with($s, 'Leo Tan ')));
        $this->assertNotEmpty(NotificationFeed::for($leo)->latest(10, includeOwn: true));
    }

    public function test_clients_only_see_what_is_shared_with_them(): void
    {
        $leo = $this->user('leo@taskflow.test');
        $web = $this->web();
        $shared = $web->tasks()->where('client_visible', true)->first();
        $hidden = $web->tasks()->where('client_visible', false)->whereNull('parent_id')->first();
        $this->actingAs($leo);

        $shared->comments()->create(['user_id' => $leo->id, 'body' => 'Ready for your review', 'is_internal' => false]);
        $shared->comments()->create(['user_id' => $leo->id, 'body' => 'Internal: client is late', 'is_internal' => true]);
        $hidden->update(['title' => 'Secret internal task']);
        $shared->update(['title' => 'Homepage, ready to review']);
        $web->sections()->create(['name' => 'Internal QA', 'position' => 50]);
        $web->docs()->create(['title' => 'Internal notes', 'content' => 'x', 'client_visible' => false]);
        $web->docs()->create(['title' => 'Brand guide', 'content' => 'x', 'client_visible' => true]);

        $client = $this->user('client@brightside.test');
        $seen = implode("\n", $this->seen($client));
        $this->assertStringContainsString('Ready for your review', $seen);
        $this->assertStringContainsString('Homepage, ready to review', $seen);
        $this->assertStringContainsString('Brand guide', $seen);
        $this->assertStringNotContainsString('client is late', $seen);
        $this->assertStringNotContainsString('Secret internal task', $seen);
        $this->assertStringNotContainsString('Internal QA', $seen);
        $this->assertStringNotContainsString('Internal notes', $seen);

        // If a shared task is later hidden, its history disappears from the portal too.
        $shared->update(['client_visible' => false]);
        $this->assertStringNotContainsString('Ready for your review', implode("\n", $this->seen($client)));

        $this->actingAs($client)->get('/portal/notifications')->assertOk()->assertSee('Brand guide')->assertDontSee('Internal notes');
    }

    public function test_bell_counts_unread_and_opening_it_marks_them_read(): void
    {
        $maya = $this->user('maya@taskflow.test');
        $maya->forceFill(['notifications_read_at' => now()->subMinute()])->save();

        $this->actingAs($this->user('leo@taskflow.test'));
        $web = $this->web();
        $web->sections()->create(['name' => 'One', 'position' => 70]);
        $web->sections()->create(['name' => 'Two', 'position' => 71]);

        $this->actingAs($maya);
        $bell = Livewire::test(\App\Livewire\Notifications\Bell::class)->assertSee('2 new')->assertDontSee('added the section');

        // Hovering loads the list ahead of time but doesn't mark anything read.
        $bell->call('prefetch')->assertSee('added the section “Two”')->assertSee('2 new');
        $this->assertSame(2, NotificationFeed::for($maya->fresh())->unreadCount());

        // Opening marks them read; they stay highlighted in the open panel.
        $bell->call('opened')->assertSeeHtml('aria-label="Notifications"')->assertSeeHtml('aria-label="New"');
        $this->assertSame(0, NotificationFeed::for($maya->fresh())->unreadCount());
    }

    public function test_notifications_page_filters_by_project_and_type(): void
    {
        $leo = $this->user('leo@taskflow.test');
        $this->actingAs($leo);
        $web = $this->web();
        $q4 = Project::where('name', 'Q4 Marketing Campaign')->first();
        $web->sections()->create(['name' => 'Web section', 'position' => 80]);
        $q4->sections()->create(['name' => 'Q4 section', 'position' => 80]);
        $web->docs()->create(['title' => 'Web doc', 'content' => 'x']);

        $this->actingAs($this->user('admin@taskflow.test'));
        Livewire::test(\App\Livewire\Notifications\Index::class)
            ->assertSee('Web section')->assertSee('Q4 section')->assertSee('Web doc')
            ->set('project', (string) $web->id)->assertSee('Web section')->assertDontSee('Q4 section')
            ->set('kind', 'doc')->assertSee('Web doc')->assertDontSee('Web section');
        $this->get('/notifications')->assertOk();

        // Jo can't filter his way into a project he can't open.
        $this->actingAs($this->user('jo@taskflow.test'));
        Livewire::test(\App\Livewire\Notifications\Index::class)->set('project', (string) $web->id)->assertDontSee('Web section');
    }

    public function test_task_history_in_the_panel_lists_only_task_events(): void
    {
        $leo = $this->user('leo@taskflow.test');
        $this->actingAs($leo);
        $task = $this->web()->tasks()->whereNull('parent_id')->first();
        $task->comments()->create(['user_id' => $leo->id, 'body' => 'hello', 'is_internal' => true]);
        $task->update(['priority' => 'high']);

        $this->assertSame(0, $task->activities()->where('kind', '!=', 'task')->count());
        $this->assertSame('set the priority to High', $task->activities()->reorder()->latest('id')->first()->description());
    }

    public function test_seeding_and_background_work_create_no_notifications(): void
    {
        $this->assertSame(0, Activity::whereNot('kind', 'task')->count());
    }
}
