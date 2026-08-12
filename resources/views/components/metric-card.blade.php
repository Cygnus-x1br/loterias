@props([
    'label',
    'value',
    'description' => null,
    'trend' => null,
    'icon' => 'chart',
    'color' => 'indigo',
])

@php
    $colors = [
        'indigo' => [
            'box' => 'bg-indigo-50 text-indigo-600',
            'trend' => 'text-indigo-600',
        ],
        'emerald' => [
            'box' => 'bg-emerald-50 text-emerald-600',
            'trend' => 'text-emerald-600',
        ],
        'violet' => [
            'box' => 'bg-violet-50 text-violet-600',
            'trend' => 'text-violet-600',
        ],
        'amber' => [
            'box' => 'bg-amber-50 text-amber-600',
            'trend' => 'text-amber-600',
        ],
        'sky' => [
            'box' => 'bg-sky-50 text-sky-600',
            'trend' => 'text-sky-600',
        ],
        'rose' => [
            'box' => 'bg-rose-50 text-rose-600',
            'trend' => 'text-rose-600',
        ],
    ];

    $theme = $colors[$color] ?? $colors['indigo'];
@endphp

<div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/50 transition hover:-translate-y-0.5 hover:shadow-md">
    <div class="flex items-start justify-between gap-4">
        <div>
            <p class="text-sm font-medium text-slate-500">
                {{ $label }}
            </p>

            <p class="mt-2 text-2xl font-extrabold tracking-tight text-slate-900">
                {{ $value }}
            </p>

            @if ($description)
                <p class="mt-1 text-xs text-slate-400">
                    {{ $description }}
                </p>
            @endif
        </div>

        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl {{ $theme['box'] }}">
            @if ($icon === 'layers')
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3l9 5-9 5-9-5 9-5zm-9 9l9 5 9-5M3 16l9 5 9-5" />
                </svg>
            @elseif ($icon === 'ticket')
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7a2 2 0 012-2h12a2 2 0 012 2v3a2 2 0 100 4v3a2 2 0 01-2 2H6a2 2 0 01-2-2v-3a2 2 0 100-4V7z" />
                </svg>
            @elseif ($icon === 'grid')
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4h6v6H4V4zm10 0h6v6h-6V4zM4 14h6v6H4v-6zm10 0h6v6h-6v-6z" />
                </svg>
            @elseif ($icon === 'clock')
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="9" stroke-width="2" />
                    <path stroke-linecap="round" stroke-width="2" d="M12 7v5l3 2" />
                </svg>
            @elseif ($icon === 'wallet')
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h11a3 3 0 013 3v10a3 3 0 01-3 3H6a2 2 0 01-2-2V6z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h13a3 3 0 013 3v1H4V8z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 15h.01" />
                </svg>
            @else
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3v18h18M7 16l3-4 3 2 5-7" />
                </svg>
            @endif
        </div>
    </div>

    @if ($trend)
        <div class="mt-4 flex items-center gap-2 border-t border-slate-100 pt-3">
            <span class="text-xs font-semibold {{ $theme['trend'] }}">
                {{ $trend }}
            </span>
        </div>
    @endif
</div>
