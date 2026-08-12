@props([
    'title' => 'Ainda não existem dados para exibir',
    'description' => 'Execute uma análise ou crie um novo registro para começar.',
    'action' => null,
])

<div class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-6 py-12 text-center">
    <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white text-slate-400 shadow-sm">
        <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 13h6m-6 4h3M7 4h7l3 3v13H7a2 2 0 01-2-2V6a2 2 0 012-2z" />
        </svg>
    </div>

    <h3 class="mt-4 text-sm font-bold text-slate-800">
        {{ $title }}
    </h3>

    <p class="mt-2 max-w-md text-sm leading-6 text-slate-500">
        {{ $description }}
    </p>

    @if ($action)
        <a
            href="{{ $action['url'] ?? '#' }}"
            class="mt-5 inline-flex items-center rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700"
        >
            {{ $action['label'] ?? 'Começar agora' }}
        </a>
    @endif
</div>
