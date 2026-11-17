{{-- Session mesajları: status (uğur), warning (diqqət), error (biznes qaydası pozuntusu) --}}
@php
    $messages = array_filter([
        'status' => ['msg' => session('status'), 'icon' => 'check', 'class' => 'border-emerald-200 bg-emerald-50 text-emerald-900'],
        'warning' => ['msg' => session('warning'), 'icon' => 'triangle-alert', 'class' => 'border-amber-200 bg-amber-50 text-amber-900'],
        'error' => ['msg' => session('error'), 'icon' => 'x', 'class' => 'border-red-200 bg-red-50 text-red-900'],
    ], fn ($m) => filled($m['msg']));
@endphp

@foreach ($messages as $type => $m)
    <div role="{{ $type === 'status' ? 'status' : 'alert' }}"
         class="mb-6 flex items-start gap-3 rounded-xl border px-4 py-3 text-sm {{ $m['class'] }}">
        <i data-lucide="{{ $m['icon'] }}" class="mt-0.5 size-4 shrink-0"></i>
        <p>{{ $m['msg'] }}</p>
    </div>
@endforeach
