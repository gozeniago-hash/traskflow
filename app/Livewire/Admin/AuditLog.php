<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\InteractsWithWorkspace;
use App\Models\AuditLog as Entry;
use App\Support\Audit;
use App\Support\Export\Csv;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Title('Audit log')]
class AuditLog extends Component
{
    use InteractsWithWorkspace;

    #[Url]
    public string $group = '';

    #[Url]
    public string $person = '';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    public int $perPage = 50;

    public function mount(): void
    {
        $this->requireAdmin();
    }

    public function updated(): void
    {
        $this->perPage = 50;
    }

    public function more(): void
    {
        $this->perPage += 50;
    }

    protected function query(): Builder
    {
        $ws = $this->workspace();
        // This workspace's entries, plus sign-in activity of its people (which isn't tied to one workspace).
        $people = $ws->users()->pluck('users.id');

        return Entry::query()
            ->where(fn (Builder $q) => $q->where('workspace_id', $ws->id)
                ->orWhere(fn (Builder $q) => $q->where('action', 'like', 'auth.%')->whereIn('user_id', $people)))
            ->when(array_key_exists($this->group, Entry::GROUPS), fn ($q) => $q->where('action', 'like', $this->group.'.%'))
            ->when($this->person !== '', fn ($q) => $q->where('user_id', (int) $this->person))
            ->when($this->from !== '' && strtotime($this->from), fn ($q) => $q->where('created_at', '>=', Carbon::parse($this->from, config('taskflow.timezone'))->startOfDay()->utc()))
            ->when($this->to !== '' && strtotime($this->to), fn ($q) => $q->where('created_at', '<=', Carbon::parse($this->to, config('taskflow.timezone'))->endOfDay()->utc()))
            ->latest('created_at')->latest('id');
    }

    public function export(): StreamedResponse
    {
        $this->requireAdmin();
        $rows = $this->query()->with('user')->limit(10000)->get();
        $tz = config('taskflow.timezone');
        Audit::log('workspace.audit_exported', 'Exported the audit log ('.$rows->count().' entries)', $this->workspace());

        return response()->streamDownload(function () use ($rows, $tz): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['When ('.$tz.')', 'Who', 'Email', 'Action', 'What happened', 'IP address']);
            foreach ($rows as $r) {
                fputcsv($out, Csv::row([
                    $r->created_at->timezone($tz)->format('Y-m-d H:i:s'), $r->user?->name ?? 'Deleted user', $r->user?->email, $r->action, $r->description, $r->ip,
                ]));
            }
            fclose($out);
        }, 'audit-log-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function render()
    {
        $entries = $this->query()->with('user')->limit($this->perPage + 1)->get();

        return view('livewire.admin.audit-log', [
            'entries' => $entries->take($this->perPage),
            'hasMore' => $entries->count() > $this->perPage,
            'people' => $this->workspace()->users()->orderBy('name')->get(['users.id', 'name']),
            'groups' => Entry::GROUPS,
            'tz' => config('taskflow.timezone'),
        ]);
    }
}
