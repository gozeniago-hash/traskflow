{{-- Custom field values shown on a task card/row. $fields: the project's fields; only show_on_card ones render. --}}
@props(['task', 'fields', 'people' => []])
@php $map = $task->relationLoaded('fieldValues') ? $task->fieldValues->pluck('value', 'field_id')->all() : []; @endphp
@foreach ($fields as $f)
    @continue(! $f->show_on_card || ! isset($map[$f->id]))
    @php $v = $map[$f->id]; @endphp
    @if ($f->type === 'select' && ($o = $f->option($v)))
        <span class="pill {{ \App\Models\ProjectField::COLORS[$o['color']] ?? '' }}" title="{{ $f->name }}">{{ $o['label'] }}</span>
    @elseif ($f->type === 'multiselect')
        @foreach (json_decode($v, true) ?: [] as $oid)
            @if ($o = $f->option($oid))<span class="pill {{ \App\Models\ProjectField::COLORS[$o['color']] ?? '' }}" title="{{ $f->name }}">{{ $o['label'] }}</span>@endif
        @endforeach
    @elseif ($f->type === 'checkbox')
        <span class="pill bg-emerald-50 text-emerald-700" title="{{ $f->name }}">✓ {{ $f->name }}</span>
    @elseif ($f->type === 'url')
        <a href="{{ $v }}" target="_blank" rel="noopener noreferrer" x-on:click.stop class="pill bg-gray-100 text-ink-700 hover:underline" title="{{ $v }}"><x-icon name="link" class="h-3 w-3" />{{ $f->name }}</a>
    @elseif ($f->type !== 'textarea')
        <span class="pill bg-gray-100 text-ink-700" title="{{ $f->name }}"><span class="text-ink-500">{{ $f->name }}:</span> {{ \Illuminate\Support\Str::limit($f->display($v, $people), 28) }}</span>
    @endif
@endforeach
