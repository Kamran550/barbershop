@props(['active'])

<span @class([
    'inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium',
    'bg-emerald-50 text-emerald-700' => $active,
    'bg-ink-100 text-ink-500' => ! $active,
])>
    <span @class(['size-1.5 rounded-full', 'bg-emerald-500' => $active, 'bg-ink-400' => ! $active])></span>
    {{ $active ? 'Aktiv' : 'Deaktiv' }}
</span>
