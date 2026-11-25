@props(['classification'])

@php
    /** @var \App\Services\Betting\ResultClassification $classification */
@endphp

<span
    data-result-classification="{{ $classification->value }}"
    title="{{ $classification->description() }}"
    {{ $attributes->class(['inline-flex items-center rounded-full border px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide', $classification->badgeClasses()]) }}
>{{ $classification->label() }}</span>
