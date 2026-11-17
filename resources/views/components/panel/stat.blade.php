@props(['label', 'value', 'icon', 'hint' => null, 'href' => null])

@php $tag = $href ? 'a' : 'div'; @endphp

<{{ $tag }} @if ($href) href="{{ $href }}" @endif
    {{ $attributes->class(['card block p-5', 'transition hover:border-ink-200 hover:shadow-sm' => $href]) }}>
    <div class="flex items-center justify-between gap-3">
        <p class="text-sm font-medium text-ink-500">{{ $label }}</p>
        <i data-lucide="{{ $icon }}" class="size-4 text-brass-500"></i>
    </div>
    <p class="mt-3 text-3xl font-semibold tracking-tight tabular-nums">{{ $value }}</p>
    @if ($hint)
        <p class="mt-1 text-xs text-ink-400">{{ $hint }}</p>
    @endif
</{{ $tag }}>
