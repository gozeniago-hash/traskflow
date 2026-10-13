<?php

namespace App\Support;

/** Starter content for new project docs. Kept to the HTML the editor produces. */
class DocTemplates
{
    public const LIST = [
        'blank' => ['Blank doc', 'Start from an empty page'],
        'requirements' => ['Project requirements', 'Goals, scope, users, requirements, acceptance criteria'],
        'tech_spec' => ['Technical spec', 'Architecture, data, integrations, risks'],
        'meeting' => ['Meeting notes', 'Attendees, agenda, decisions, action items'],
        'brief' => ['Creative brief', 'Audience, message, deliverables, timeline'],
    ];

    public static function title(string $key): string
    {
        return $key === 'blank' ? 'Untitled doc' : (self::LIST[$key][0] ?? 'Untitled doc');
    }

    public static function content(string $key, string $projectName): string
    {
        $p = e($projectName);
        $today = now()->format('F j, Y');

        return match ($key) {
            'requirements' => <<<HTML
<h1>Overview</h1><div>What is {$p} and why are we doing it? Summarize the problem and the outcome in two or three sentences.</div>
<h1>Goals</h1><ul><li>Goal 1 — how we'll measure it</li><li>Goal 2 — how we'll measure it</li></ul>
<h1>Out of scope</h1><ul><li>What we are deliberately not doing</li></ul>
<h1>Users &amp; stakeholders</h1><ul><li><strong>Primary users:</strong> who they are and what they need</li><li><strong>Approvers:</strong> who signs off</li></ul>
<h1>Requirements</h1><ol><li><strong>Must have:</strong> describe the requirement</li><li><strong>Should have:</strong> describe the requirement</li><li><strong>Nice to have:</strong> describe the requirement</li></ol>
<h1>Acceptance criteria</h1><ul><li>Given … when … then …</li></ul>
<h1>Timeline &amp; milestones</h1><ul><li>Milestone — target date</li></ul>
<h1>Open questions</h1><ul><li>Question — owner</li></ul>
HTML,
            'tech_spec' => <<<HTML
<h1>Summary</h1><div>One paragraph describing the technical approach for {$p}.</div>
<h1>Architecture</h1><div>Components, how they talk to each other, hosting.</div>
<h1>Data model</h1><ul><li>Entity — key fields</li></ul>
<h1>Integrations &amp; APIs</h1><ul><li>Service — purpose, auth, rate limits</li></ul>
<h1>Security &amp; privacy</h1><ul><li>Auth, roles, data retention</li></ul>
<h1>Risks &amp; mitigations</h1><ul><li>Risk — mitigation</li></ul>
<h1>Rollout plan</h1><ol><li>Staging</li><li>Client UAT</li><li>Production</li></ol>
HTML,
            'meeting' => <<<HTML
<h1>Meeting — {$today}</h1><div><strong>Attendees:</strong> </div>
<h1>Agenda</h1><ol><li></li></ol>
<h1>Notes</h1><ul><li></li></ul>
<h1>Decisions</h1><ul><li></li></ul>
<h1>Action items</h1><ul><li>Action — owner — due date</li></ul>
HTML,
            'brief' => <<<HTML
<h1>Background</h1><div>Why {$p} exists and what success looks like.</div>
<h1>Audience</h1><div>Who we're talking to and what they care about.</div>
<h1>Key message</h1><blockquote>The one thing we want people to remember.</blockquote>
<h1>Deliverables</h1><ul><li>Asset — format — size</li></ul>
<h1>Brand &amp; tone</h1><ul><li>Do: </li><li>Don't: </li></ul>
<h1>Timeline &amp; budget</h1><ul><li>Milestone — date</li></ul>
HTML,
            default => '',
        };
    }
}
