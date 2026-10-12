<div>
    <p class="text-sm text-ink-500">{{ $client?->name ?? 'Client portal' }}</p>
    <h1 class="mt-1 text-2xl font-semibold">Welcome, {{ auth()->user()->firstName() }}</h1>
    <p class="mt-1 text-sm text-ink-500">Here's where things stand with {{ $workspace->name }}.</p>

    @if ($approvals->isNotEmpty())
        <section class="mt-6 rounded-xl border border-violet-200 bg-violet-50 p-5">
            <h2 class="flex items-center gap-2 font-semibold text-violet-900"><x-icon name="thumb-up" /> Waiting for your approval ({{ $approvals->count() }})</h2>
            <ul class="mt-3 space-y-2">
                @foreach ($approvals as $t)
                    <li>
                        <a href="{{ route('portal.projects.show', ['project' => $t->project_id, 'tab' => 'tasks', 'task' => $t->id]) }}" wire:navigate
                           class="flex items-center gap-3 rounded-lg bg-white px-4 py-2.5 text-sm shadow-sm hover:shadow">
                            <span class="h-2.5 w-2.5 rounded-[3px]" style="background-color: {{ $t->project->color }}"></span>
                            <span class="flex-1 truncate font-medium">{{ $t->title }}</span>
                            <span class="hidden text-xs text-ink-500 sm:inline">{{ $t->project->name }}</span>
                            <span class="font-medium text-violet-700">Review →</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <h2 class="mb-3 mt-8 font-semibold">Your projects</h2>
    <div class="grid gap-4 md:grid-cols-2">
        @forelse ($projects as $p)
            @php($pct = $p->shared_total ? (int) round($p->shared_done / $p->shared_total * 100) : 0)
            @php($latest = $p->statusUpdates->first())
            <a wire:key="pp-{{ $p->id }}" href="{{ route('portal.projects.show', $p) }}" wire:navigate class="card block bg-white p-5 transition hover:shadow-md">
                <div class="flex items-start gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-white" style="background-color: {{ $p->color }}"><x-icon name="folder" class="h-5 w-5" /></span>
                    <div class="min-w-0 flex-1">
                        <h3 class="truncate font-semibold">{{ $p->name }}</h3>
                        <p class="text-xs text-ink-500">@if ($p->due_date)Target: {{ $p->due_date->format('F j, Y') }}@else No target date set @endif</p>
                    </div>
                    <x-status-pill :status="$p->status" />
                </div>
                <div class="mt-4">
                    <div class="mb-1.5 flex justify-between text-xs text-ink-500"><span>{{ $p->shared_done }} of {{ $p->shared_total }} milestones done</span><span>{{ $pct }}%</span></div>
                    <x-progress-bar :percent="$pct" :color="$p->color" class="h-2" />
                </div>
                @if ($latest)
                    <div class="mt-4 rounded-lg bg-gray-50 p-3">
                        <p class="text-xs text-ink-500">Latest update · {{ $latest->created_at->diffForHumans() }}</p>
                        <p class="mt-0.5 text-sm font-medium">{{ $latest->title }}</p>
                    </div>
                @endif
            </a>
        @empty
            <x-empty class="bg-white md:col-span-2" icon="folder" title="No projects shared yet" text="When the team shares a project with you, it will appear here." />
        @endforelse
    </div>
</div>
