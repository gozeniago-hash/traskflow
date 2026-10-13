<?php

use App\Models\Project;
use App\Models\ProjectDoc;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;

/** Sample docs for the demo workspace. */
return new class extends Migration
{
    public function up(): void
    {
        if (! config('taskflow.seed_demo_data') || ProjectDoc::query()->exists()) {
            return;
        }

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

    public function down(): void
    {
    }
};
