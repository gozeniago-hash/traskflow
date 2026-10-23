@props(['status'])
@php($status = $status instanceof \App\Enums\ApprovalStatus ? $status : \App\Enums\ApprovalStatus::tryFrom((string) $status))
@if ($status)
    <span {{ $attributes->merge(['class' => 'pill '.$status->pillClasses()]) }}>{{ $status->label() }}</span>
@endif
