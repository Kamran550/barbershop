@props(['status'])

@php
    $tone = match ($status) {
        \App\Enums\AppointmentStatus::Pending => 'bg-amber-50 text-amber-800 ring-amber-200',
        \App\Enums\AppointmentStatus::Confirmed => 'bg-sky-50 text-sky-800 ring-sky-200',
        \App\Enums\AppointmentStatus::Completed => 'bg-emerald-50 text-emerald-800 ring-emerald-200',
        \App\Enums\AppointmentStatus::NoShow => 'bg-red-50 text-red-700 ring-red-200',
        \App\Enums\AppointmentStatus::Cancelled => 'bg-ink-100 text-ink-500 ring-ink-200',
    };
@endphp

<span {{ $attributes->class(['inline-flex shrink-0 items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1', $tone]) }}>
    {{ $status->label() }}
</span>
