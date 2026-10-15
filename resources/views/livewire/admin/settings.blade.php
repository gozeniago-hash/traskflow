<div>
    <x-page-header title="Workspace settings" />
    <div class="grid max-w-5xl gap-6 p-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <form wire:submit="save" class="card space-y-4 p-5">
                <h2 class="font-semibold">General</h2>
                <div>
                    <label class="label">Workspace name</label>
                    <input wire:model="name" class="input">
                    @error('name')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div class="flex justify-end"><button class="btn-primary btn">Save</button></div>
            </form>

            {{-- Security --}}
            <div class="card p-5">
                <h2 class="flex items-center gap-2 font-semibold"><x-icon name="lock" class="h-5 w-5 text-ink-700" /> Security</h2>
                <label class="mt-3 flex items-start gap-3">
                    <input type="checkbox" class="mt-0.5 rounded border-gray-300 text-coral-600 focus:ring-coral-400" @checked($security['required'])
                           wire:change="setRequireTwoFactor($event.target.checked)" aria-describedby="tfa-req-help">
                    <span>
                        <span class="text-sm font-medium">Require two-step verification for the team</span>
                        <span id="tfa-req-help" class="mt-0.5 block text-sm text-ink-500">Admins, managers and members must use an authenticator app when they sign in. Anyone who hasn’t set it up is asked to before they can continue. Client portal users aren’t affected.</span>
                        <span class="mt-1 block text-sm {{ $security['without'] ? 'text-amber-800' : 'text-emerald-700' }}">{{ $security['without'] ? $security['without'].' '.\Illuminate\Support\Str::plural('teammate', $security['without']).' haven’t turned it on yet.' : 'Everyone on the team has it on.' }}</span>
                    </span>
                </label>
                <a href="{{ route('audit-log') }}" wire:navigate class="mt-4 inline-flex text-sm font-medium text-coral-700 hover:underline">View the audit log →</a>
            </div>

            {{-- GitHub --}}
            <div class="card p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="flex items-center gap-2 font-semibold"><x-icon name="git-pull" class="h-5 w-5 text-ink-700" /> GitHub</h2>
                        <p class="mt-1 text-sm text-ink-500">Pull requests that mention a ticket number (like <span class="font-mono text-xs">BWR-12</span>) in their title, branch name or description show up on that task.</p>
                    </div>
                    @if ($github['connected'])
                        <span class="pill shrink-0 bg-emerald-50 text-emerald-700">Connected as {{ '@'.$github['login'] }}</span>
                    @endif
                </div>

                @if (! $github['connected'])
                    <form wire:submit="connectGitHub" class="mt-4 space-y-3">
                        <div>
                            <label class="label" for="gh-token">Access token</label>
                            <input id="gh-token" type="password" wire:model="githubToken" autocomplete="off" placeholder="github_pat_…" class="input font-mono">
                            @error('githubToken')<p class="error">{{ $message }}</p>@enderror
                        </div>
                        <details class="rounded-lg bg-gray-50 px-3 py-2 text-sm text-ink-700">
                            <summary class="cursor-pointer font-medium">How to create a token</summary>
                            <ol class="mt-2 list-decimal space-y-1 pl-5 text-ink-700">
                                <li>On GitHub, open <a href="https://github.com/settings/personal-access-tokens/new" target="_blank" rel="noopener" class="text-coral-600 hover:underline">Settings → Developer settings → Fine-grained tokens → Generate new token</a>.</li>
                                <li>Pick the account or organization that owns your repositories, and choose the repositories to share.</li>
                                <li>Under <strong>Repository permissions</strong>, set <strong>Pull requests</strong> to <em>Read-only</em>. Optionally set <strong>Webhooks</strong> to <em>Read and write</em> so TaskFlow can set up live updates for you.</li>
                                <li>Generate the token and paste it here. It's stored encrypted and never shown again.</li>
                            </ol>
                        </details>
                        <div class="flex justify-end"><button class="btn-primary btn" wire:loading.attr="disabled" wire:target="connectGitHub">Connect GitHub</button></div>
                    </form>
                @else
                    <form wire:submit="addRepository" class="mt-4 flex items-start gap-2">
                        <div class="flex-1">
                            <input wire:model="newRepo" placeholder="owner/repository or a github.com link" class="input">
                            @error('newRepo')<p class="error">{{ $message }}</p>@enderror
                        </div>
                        <button class="btn-secondary btn h-[38px] shrink-0" wire:loading.attr="disabled" wire:target="addRepository">
                            <x-icon name="plus" /> <span wire:loading.remove wire:target="addRepository">Add repository</span><span wire:loading wire:target="addRepository">Adding…</span>
                        </button>
                    </form>

                    <ul class="mt-4 divide-y divide-gray-100 border-t border-gray-100">
                        @forelse ($github['repos'] as $repo)
                            <li wire:key="gh-{{ $repo->id }}" class="py-3 text-sm">
                                <div class="flex items-center gap-2">
                                    <a href="{{ $repo->htmlUrl() }}" target="_blank" rel="noopener" class="min-w-0 flex-1 truncate font-medium hover:underline">{{ $repo->full_name }}</a>
                                    <button wire:click="syncRepository({{ $repo->id }})" wire:loading.attr="disabled" wire:target="syncRepository({{ $repo->id }})" class="btn-ghost btn text-xs">
                                        <x-icon name="refresh" class="h-3.5 w-3.5" wire:loading.class="animate-spin" wire:target="syncRepository({{ $repo->id }})" /> Sync now
                                    </button>
                                    <button wire:click="removeRepository({{ $repo->id }})" wire:confirm="Remove {{ $repo->full_name }}? Its pull requests will no longer show on tasks." class="btn-ghost btn px-1.5 text-red-600" title="Remove"><x-icon name="trash" /></button>
                                </div>
                                <p class="mt-0.5 text-xs text-ink-500">
                                    {{ $repo->pull_requests_count }} {{ \Illuminate\Support\Str::plural('pull request', $repo->pull_requests_count) }}
                                    · {{ $repo->last_synced_at ? 'synced '.$repo->last_synced_at->diffForHumans() : 'never synced' }}
                                    · @if ($repo->webhook_id || $repo->last_webhook_at)
                                        <span class="text-emerald-700">live updates on</span>
                                    @else
                                        <button type="button" wire:click="toggleWebhookHelp({{ $repo->id }})" class="text-amber-700 hover:underline">set up live updates</button>
                                    @endif
                                </p>
                                @if ($repo->last_error)
                                    <p class="mt-1 text-xs text-red-600">{{ $repo->last_error }}</p>
                                @endif
                                @if ($showWebhookFor === $repo->id)
                                    <div class="mt-2 space-y-2 rounded-lg bg-amber-50 p-3 text-xs text-ink-800">
                                        <p>TaskFlow couldn't add the webhook itself. In GitHub, open <a href="{{ $repo->htmlUrl() }}/settings/hooks/new" target="_blank" rel="noopener" class="font-medium text-coral-600 hover:underline">{{ $repo->full_name }} → Settings → Webhooks → Add webhook</a> and enter:</p>
                                        @foreach (['Payload URL' => $repo->webhookUrl(), 'Secret' => $repo->webhook_secret] as $label => $value)
                                            <div class="flex items-center gap-2">
                                                <span class="w-24 shrink-0 font-medium">{{ $label }}</span>
                                                <code class="min-w-0 flex-1 truncate rounded bg-white px-2 py-1 font-mono">{{ $value }}</code>
                                                <button type="button" x-data x-on:click="navigator.clipboard.writeText(@js($value)); $dispatch('toast', { message: '{{ $label }} copied' })" class="btn-ghost btn px-1.5" title="Copy"><x-icon name="copy" /></button>
                                            </div>
                                        @endforeach
                                        <p>Content type: <strong>application/json</strong>. Events: choose <strong>Let me select individual events</strong> and tick only <strong>Pull requests</strong>.</p>
                                    </div>
                                @endif
                            </li>
                        @empty
                            <li class="py-3 text-sm text-ink-500">No repositories yet. Add one above.</li>
                        @endforelse
                    </ul>
                    <div class="mt-3 flex justify-end">
                        <button wire:click="disconnectGitHub" wire:confirm="Disconnect GitHub? Pull requests stop updating until you connect again." class="btn-ghost btn text-xs text-ink-500">Disconnect GitHub</button>
                    </div>
                @endif
            </div>

            <div class="card p-5">
                <h2 class="font-semibold">Archived projects</h2>
                <ul class="mt-3 divide-y divide-gray-100">
                    @forelse ($archived as $p)
                        <li wire:key="ar-{{ $p->id }}" class="flex items-center gap-2 py-2 text-sm">
                            <span class="h-2.5 w-2.5 rounded-[3px]" style="background-color: {{ $p->color }}"></span>
                            <span class="flex-1">{{ $p->name }}</span>
                            <span class="text-xs text-ink-500">archived {{ $p->archived_at->diffForHumans() }}</span>
                            <button wire:click="unarchive({{ $p->id }})" class="btn-ghost btn text-xs"><x-icon name="refresh" /> Restore</button>
                        </li>
                    @empty
                        <li class="py-2 text-sm text-ink-500">Nothing archived.</li>
                    @endforelse
                </ul>
            </div>
        </div>
        <div class="card h-fit p-5">
            <h2 class="font-semibold">At a glance</h2>
            <dl class="mt-3 space-y-2 text-sm">
                @foreach ($stats as $label => $value)
                    <div class="flex justify-between"><dt class="text-ink-500">{{ $label }}</dt><dd class="font-medium">{{ $value }}</dd></div>
                @endforeach
            </dl>
            @php($fmt = fn ($b) => $b >= 1024 ** 3 ? round($b / 1024 ** 3, 1).' GB' : ($b >= 1024 ** 2 ? round($b / 1024 ** 2, 1).' MB' : round($b / 1024).' KB'))
            <div class="mt-4 border-t border-gray-100 pt-4 text-sm">
                <div class="flex justify-between"><span class="text-ink-500">File storage</span><span class="font-medium">{{ $fmt($storage['used']) }}{{ $storage['quota'] ? ' of '.$fmt($storage['quota']) : '' }}</span></div>
                @if ($storage['quota'])
                    @php($pct = min(100, round($storage['used'] / $storage['quota'] * 100)))
                    <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-gray-100" role="progressbar" aria-label="File storage used" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100">
                        <div class="h-full rounded-full {{ $pct >= 90 ? 'bg-red-500' : 'bg-coral-500' }}" style="width: {{ $pct }}%"></div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
