<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\Project;
use App\Models\ProjectField;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Seeder;

/**
 * Demo workspace so you can click around right away.
 *
 *   Owner:   admin@taskflow.test  / password
 *   Member:  maya@taskflow.test   / password
 *   Client:  client@brightside.test / password   (client portal)
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::create(['name' => 'Alex Rivera', 'email' => 'admin@taskflow.test', 'password' => 'password', 'title' => 'Operations Lead', 'avatar_color' => '#f06a5a']);
        $maya = User::create(['name' => 'Maya Santos', 'email' => 'maya@taskflow.test', 'password' => 'password', 'title' => 'Product Designer', 'avatar_color' => '#4573d2']);
        $leo = User::create(['name' => 'Leo Tan', 'email' => 'leo@taskflow.test', 'password' => 'password', 'title' => 'Full-stack Developer', 'avatar_color' => '#5da283']);
        $jo = User::create(['name' => 'Jo Bautista', 'email' => 'jo@taskflow.test', 'password' => 'password', 'title' => 'Marketing Manager', 'avatar_color' => '#aa62e3']);
        $clientUser = User::create(['name' => 'Dana Cruz', 'email' => 'client@brightside.test', 'password' => 'password', 'title' => 'Head of Marketing, Brightside', 'avatar_color' => '#fd9a00']);

        $ws = Workspace::create(['name' => 'Acme Studio', 'owner_id' => $owner->id]);
        $client = $ws->clients()->create([
            'name' => 'Brightside Co.', 'contact_name' => 'Dana Cruz', 'email' => 'client@brightside.test',
            'phone' => '+63 900 000 0000', 'website' => 'brightside.example', 'notes' => 'Prefers weekly Friday updates.',
        ]);

        $ws->users()->attach([
            $owner->id => ['role' => 'owner'],
            $maya->id => ['role' => 'admin'],
            $leo->id => ['role' => 'member'],
            $jo->id => ['role' => 'member'],
        ]);
        // Attached separately: a batch insert needs the same columns on every row.
        $ws->users()->attach($clientUser->id, ['role' => 'client', 'client_id' => $client->id]);
        foreach ([$owner, $maya, $leo, $jo, $clientUser] as $u) {
            $u->forceFill(['current_workspace_id' => $ws->id])->save();
        }

        $product = $ws->teams()->create(['name' => 'Product & Engineering', 'description' => 'Designers and developers shipping client work.']);
        $product->users()->attach([$owner->id, $maya->id, $leo->id]);
        $marketing = $ws->teams()->create(['name' => 'Marketing', 'description' => 'Campaigns, content and launches.']);
        $marketing->users()->attach([$owner->id, $jo->id]);

        // ------------------------------------------------ Client project (portal on)
        $web = $this->project($ws, [
            'name' => 'Brightside Website Redesign', 'team_id' => $product->id, 'client_id' => $client->id, 'owner_id' => $owner->id,
            'color' => '#4573d2', 'status' => 'on_track', 'privacy' => 'team', 'default_view' => 'board', 'portal_enabled' => true,
            'start_date' => now()->subWeeks(3), 'due_date' => now()->addWeeks(6),
            'description' => "Redesign brightside.example with a faster, conversion-focused site.\n\nGoals: lift demo requests 30%, cut page load under 2s, make content editable by the marketing team.",
        ], [$owner, $maya, $leo], [
            'Discovery' => [
                ['Kickoff workshop with Brightside', $owner, -18, true, 'none', true],
                ['Audit current site analytics', $leo, -14, true, 'medium', false],
                ['Sitemap and content inventory', $maya, -10, true, 'medium', true],
            ],
            'Design' => [
                ['Homepage wireframes', $maya, -4, true, 'high', true, 'approved'],
                ['Visual design: homepage and pricing', $maya, 2, false, 'high', true, 'pending'],
                ['Design system tokens', $maya, 6, false, 'medium', false],
            ],
            'Build' => [
                ['Set up CMS and staging environment', $leo, 5, false, 'high', false],
                ['Build homepage template', $leo, 12, false, 'medium', true],
                ['Pricing page with plan comparison', $leo, 18, false, 'medium', true],
            ],
            'Client review' => [
                ['Copy review round 1', $owner, 9, false, 'medium', true],
            ],
            'Launch' => [
                ['Go-live checklist and DNS cutover', $leo, 40, false, 'high', true],
            ],
        ]);

        $web->statusUpdates()->create(['user_id' => $owner->id, 'status' => 'on_track', 'title' => 'Wireframes approved — visual design underway',
            'body' => "Dana signed off on the homepage wireframes on Monday.\nThis week: homepage and pricing visuals, CMS setup on staging.\nRisk: we still need final pricing copy by next Friday.", 'client_visible' => true]);
        $web->statusUpdates()->forceCreate(['project_id' => $web->id, 'user_id' => $owner->id, 'status' => 'on_track', 'title' => 'Discovery complete',
            'body' => 'Workshop done, analytics audit shared, sitemap agreed.', 'client_visible' => true, 'created_at' => now()->subWeeks(2)]);

        $visual = $web->tasks()->where('title', 'Visual design: homepage and pricing')->first();
        $visual->comments()->create(['user_id' => $maya->id, 'body' => 'First pass on the homepage is attached in Figma. Pricing page follows tomorrow.', 'is_internal' => false]);
        $visual->comments()->create(['user_id' => $clientUser->id, 'body' => 'Love the hero! Could we try a warmer background color?', 'is_internal' => false]);
        $visual->comments()->create(['user_id' => $leo->id, 'body' => 'Heads-up: the animated hero will need a lighter video for mobile.', 'is_internal' => true]);
        foreach (['Pick hero photography', 'Pricing table states', 'Mobile breakpoints'] as $i => $title) {
            $visual->subtasks()->create(['project_id' => $web->id, 'title' => $title, 'assignee_id' => $maya->id, 'position' => $i + 1, 'completed_at' => $i === 0 ? now() : null]);
        }

        // ---------------------------------------------------- Internal project
        $this->project($ws, [
            'name' => 'Mobile App v2 Launch', 'team_id' => $product->id, 'owner_id' => $maya->id,
            'color' => '#37c5ab', 'status' => 'at_risk', 'privacy' => 'team', 'default_view' => 'list',
            'due_date' => now()->addWeeks(4), 'description' => 'Ship v2 of the companion app to the App Store and Google Play.',
        ], [$maya, $leo, $owner], [
            'To do' => [
                ['Write release notes', $jo, 20, false, 'low', false],
                ['App Store screenshots', $maya, 14, false, 'medium', false],
            ],
            'In progress' => [
                ['Offline mode sync', $leo, -1, false, 'high', false],
                ['Push notification preferences', $leo, 7, false, 'medium', false],
            ],
            'In review' => [
                ['Onboarding flow redesign', $maya, 1, false, 'high', false],
            ],
            'Done' => [
                ['Crash reporting setup', $leo, -6, true, 'medium', false],
            ],
        ])->statusUpdates()->create(['user_id' => $maya->id, 'status' => 'at_risk', 'title' => 'Offline sync is slipping', 'body' => 'Conflict resolution is harder than planned. Proposing we cut background sync from v2.', 'client_visible' => false]);

        // --------------------------------------------------- Marketing project
        $this->project($ws, [
            'name' => 'Q4 Marketing Campaign', 'team_id' => $marketing->id, 'owner_id' => $jo->id,
            'color' => '#ea4e9d', 'status' => 'on_track', 'privacy' => 'workspace', 'default_view' => 'list',
            'due_date' => now()->addWeeks(8), 'description' => 'Holiday campaign across email, social and paid search.',
        ], [$jo, $owner], [
            'Planning' => [
                ['Campaign brief and budget', $jo, -3, true, 'high', false],
                ['Pick channels and KPIs', $owner, 0, false, 'medium', false],
            ],
            'Content' => [
                ['Email sequence (3 emails)', $jo, 10, false, 'medium', false],
                ['Social calendar', $jo, 12, false, 'low', false],
                ['Landing page copy', null, 15, false, 'none', false],
            ],
        ]);

        $this->seedTimeEntries();
        $this->seedDocs();
        $this->seedFields();
    }

    /** Example custom fields on the Brightside project. Safe to run more than once. */
    public function seedFields(): void
    {
        $web = Project::where('name', 'Brightside Website Redesign')->first();
        if (! $web || $web->fields()->exists()) {
            return;
        }

        $type = $web->fields()->create([
            'name' => 'Type', 'type' => 'select', 'position' => 1, 'show_on_card' => true, 'client_visible' => true,
            'options' => ProjectField::makeOptions(['Feature' => 'blue', 'Bug' => 'red', 'Design' => 'purple', 'Content' => 'amber']),
        ]);
        $points = $web->fields()->create(['name' => 'Story points', 'type' => 'number', 'position' => 2, 'show_on_card' => true]);
        $web->fields()->create(['name' => 'Figma link', 'type' => 'url', 'position' => 3, 'show_on_card' => false, 'description' => 'Link to the design file']);

        $values = [
            'Homepage wireframes' => ['Design', 5],
            'Visual design: homepage and pricing' => ['Design', 8],
            'Design system tokens' => ['Design', 3],
            'Audit current site analytics' => ['Content', 3],
            'Set up CMS and staging environment' => ['Feature', 5],
            'Build homepage template' => ['Feature', 8],
            'Pricing page with plan comparison' => ['Feature', 5],
            'Copy review round 1' => ['Content', 2],
            'Go-live checklist and DNS cutover' => ['Feature', 3],
        ];
        $optionIds = collect($type->optionList())->pluck('id', 'label');

        foreach ($values as $title => [$label, $sp]) {
            if ($task = $web->tasks()->where('title', $title)->first()) {
                $task->fieldValues()->create(['field_id' => $type->id, 'value' => $optionIds[$label]]);
                $task->fieldValues()->create(['field_id' => $points->id, 'value' => (string) $sp]);
            }
        }
    }

    protected function seedTimeEntries(): void
    {
        $samples = [
            ['Homepage wireframes', 'maya@taskflow.test', [3.5, 2.0, 1.25]],
            ['Visual design: homepage and pricing', 'maya@taskflow.test', [2.5, 1.75]],
            ['Audit current site analytics', 'leo@taskflow.test', [1.5]],
            ['Offline mode sync', 'leo@taskflow.test', [4.0, 3.0]],
            ['Campaign brief and budget', 'jo@taskflow.test', [2.0]],
        ];

        foreach ($samples as [$title, $email, $hours]) {
            $task = Task::where('title', $title)->first();
            $user = User::where('email', $email)->first();
            if (! $task || ! $user) {
                continue;
            }
            foreach ($hours as $i => $h) {
                $start = now()->subDays(count($hours) - $i + 1)->setTime(9 + $i, 0);
                TimeEntry::create([
                    'project_id' => $task->project_id,
                    'task_id' => $task->id,
                    'user_id' => $user->id,
                    'started_at' => $start,
                    'ended_at' => $start->copy()->addSeconds((int) ($h * 3600)),
                    'duration_seconds' => (int) ($h * 3600),
                ]);
            }
        }
    }

    protected function seedDocs(): void
    {
        $web = Project::where('name', 'Brightside Website Redesign')->first();
        $owner = User::where('email', 'admin@taskflow.test')->first();
        $leo = User::where('email', 'leo@taskflow.test')->first();
        if (! $web || ! $owner) {
            return;
        }

        $web->docs()->create([
            'title' => 'Website requirements',
            'client_visible' => true,
            'position' => 1,
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
            'content' => <<<'HTML'
<h1>Overview</h1><div>Brightside's current site is slow, hard to update, and doesn't explain the product clearly. The redesign gives the marketing team a fast, conversion-focused site they can edit themselves.</div>
<h1>Goals</h1><ul><li><strong>+30% demo requests</strong> within 90 days of launch</li><li><strong>Under 2 seconds</strong> load time on mobile (Largest Contentful Paint)</li><li>Marketing can publish pages without a developer</li></ul>
<h1>Out of scope</h1><ul><li>Customer login area (phase 2)</li><li>Blog migration of posts older than 2023</li></ul>
<h1>Pages</h1><ol><li>Home</li><li>Product (3 feature pages)</li><li>Pricing with plan comparison</li><li>About &amp; careers</li><li>Contact / book a demo</li></ol>
<h1>Requirements</h1><ul><li><strong>Must have:</strong> CMS with page builder blocks, demo-request form connected to HubSpot, SEO metadata per page, cookie consent</li><li><strong>Should have:</strong> customer logo wall, testimonial carousel</li><li><strong>Nice to have:</strong> pricing calculator</li></ul>
<h1>Acceptance criteria</h1><ul><li>All pages score 90+ on Lighthouse performance (mobile)</li><li>Form submissions appear in HubSpot within 1 minute</li><li>Marketing can create a new landing page from blocks in under 15 minutes</li></ul>
<h1>Open questions</h1><ul><li>Final pricing copy — <em>Dana, due next Friday</em></li></ul>
HTML,
        ]);

        $web->docs()->create([
            'title' => 'Technical notes (internal)',
            'client_visible' => false,
            'position' => 2,
            'created_by' => ($leo ?? $owner)->id,
            'updated_by' => ($leo ?? $owner)->id,
            'content' => <<<'HTML'
<h1>Stack</h1><ul><li>Headless CMS + static front end, deployed on the CDN</li><li>Images served as WebP/AVIF with responsive sizes</li></ul>
<h1>Integrations</h1><ul><li>HubSpot forms API for demo requests</li><li>Google Tag Manager — events: <code>demo_request</code>, <code>pricing_toggle</code></li></ul>
<h1>Risks</h1><ul><li>Hero video is 8 MB — needs a lighter mobile version</li><li>DNS is managed by Brightside's IT; schedule the cutover with them a week ahead</li></ul>
HTML,
        ]);
    }

    /**
     * @param  array<string, array<int, array>>  $sections  name => [[title, assignee, dueInDays, done, priority, clientVisible, approval?], ...]
     */
    protected function project(Workspace $ws, array $attributes, array $members, array $sections): Project
    {
        $project = $ws->projects()->create($attributes);
        $project->members()->attach(collect($members)->pluck('id'));

        $pos = 0;
        foreach ($sections as $name => $tasks) {
            $section = $project->sections()->create(['name' => $name, 'position' => ++$pos]);
            foreach ($tasks as $i => $row) {
                [$title, $assignee, $due, $done, $priority, $visible] = $row;
                $task = $project->tasks()->create([
                    'section_id' => $section->id,
                    'title' => $title,
                    'assignee_id' => $assignee?->id,
                    'creator_id' => $attributes['owner_id'],
                    'due_date' => now()->addDays($due)->toDateString(),
                    'completed_at' => $done ? now()->addDays(min($due, 0))->setTime(16, 0) : null,
                    'completed_by' => $done ? $assignee?->id : null,
                    'priority' => $priority,
                    'client_visible' => $visible,
                    'approval_status' => $row[6] ?? null,
                    'approval_at' => ($row[6] ?? null) === 'approved' ? now()->subDays(2) : null,
                    'position' => $i + 1,
                ]);
                Activity::forceCreate(['project_id' => $project->id, 'task_id' => $task->id, 'user_id' => $attributes['owner_id'], 'action' => 'created', 'created_at' => now()->subDays(20)]);
            }
        }

        return $project;
    }
}
