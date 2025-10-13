@props([
    'icon' => null,
    'title' => '',
    'value' => '',
    'delta' => null,
    'deltaType' => null, // up|down|null
    'helper' => null,
    'class' => ''
])
@php
    $arrow = $deltaType === 'up' ? 'M5 12l5 5L20 7' : ($deltaType === 'down' ? 'M19 12l-5-5L4 17' : null);
    $deltaVariant = $deltaType === 'up' ? 'success' : ($deltaType === 'down' ? 'danger' : 'neutral');
@endphp
<x-card :class="'flex flex-col gap-3 '.$class">
    <div class="flex items-start justify-between">
        <div class="w-9 h-9 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
            {{-- Simple fallback icon --}}
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4">
                @switch($icon)
                    @case('users')
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 11c1.657 0 3-1.567 3-3.5S17.657 4 16 4s-3 1.567-3 3.5 1.343 3.5 3 3.5Zm0 0v1c0 1.657-2.686 3-6 3s-6-1.343-6-3v-1" />
                        @break
                    @case('package')
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 7.5 12 12l9-4.5M3 7.5 12 3l9 4.5M3 7.5V16.5L12 21l9-4.5V7.5" />
                        @break
                    @case('truck')
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16V6h11v10m0 0h3l3-5h-3l-3 5Zm-9 2a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm10 0a2 2 0 1 0 .001-3.999A2 2 0 0 0 15 18Z" />
                        @break
                    @default
                        <circle cx="12" cy="12" r="9" />
                @endswitch
            </svg>
        </div>
        @if($delta)
            <x-badge :variant="$deltaVariant">
                @if($arrow)
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $arrow }}" />
                    </svg>
                @endif
                {{ $delta }}
            </x-badge>
        @endif
    </div>
    <div class="space-y-1">
        <div class="text-sm text-slate-500">{{ $title }}</div>
        <div class="text-2xl font-semibold tracking-tight text-slate-900">{{ $value }}</div>
        @if($helper)
            <div class="text-xs text-slate-500">{{ $helper }}</div>
        @endif
    </div>
</x-card>

