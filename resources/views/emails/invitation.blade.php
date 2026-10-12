<x-mail::message>
# You're invited

{{ $invitation->inviter?->name ?? 'A teammate' }} invited you to
@if ($invitation->role === 'client')
the **{{ $invitation->workspace->name }}** client portal{{ $invitation->client ? ' for '.$invitation->client->name : '' }}, where you can follow project progress, review files and approve work.
@else
join **{{ $invitation->workspace->name }}** on TaskFlow.
@endif

<x-mail::button :url="$invitation->url()">
Accept invitation
</x-mail::button>

This link expires on {{ $invitation->expires_at->format('F j, Y') }}.

Thanks,<br>
{{ $invitation->workspace->name }}
</x-mail::message>
