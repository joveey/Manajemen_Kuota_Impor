@props([
    'variant' => 'neutral',
    'class' => ''
])
@php
    $variants = [
        'success' => 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200',
        'warning' => 'bg-amber-50 text-amber-700 ring-1 ring-amber-200',
        'danger'  => 'bg-rose-50 text-rose-700 ring-1 ring-rose-200',
        'info'    => 'bg-blue-50 text-blue-700 ring-1 ring-blue-200',
        'neutral' => 'bg-slate-100 text-slate-700 ring-1 ring-slate-200',
    ];
    $styles = $variants[$variant] ?? $variants['neutral'];
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium '.$styles.' '.$class]) }}>
    {{ $slot }}
  </span>

