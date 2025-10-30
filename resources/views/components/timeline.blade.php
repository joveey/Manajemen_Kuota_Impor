@props([
    'items' => [],
    'class' => ''
])
<div {{ $attributes->merge(['class' => 'relative '.$class]) }}>
    <ol class="relative border-s-2 border-slate-200 ps-5 space-y-5">
        @forelse($items as $item)
            <li class="relative">
                <span class="absolute -start-2 top-2 w-3.5 h-3.5 rounded-full bg-blue-500 ring-4 ring-white"></span>
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="text-sm font-medium text-slate-800">{{ $item['title'] ?? '-' }}</div>
                        @if(!empty($item['subtitle']))
                            <div class="text-xs text-slate-500">{{ $item['subtitle'] }}</div>
                        @endif
                    </div>
                    <div class="text-xs text-slate-500 whitespace-nowrap">{{ $item['date'] ?? '' }}</div>
                </div>
                @if(!empty($item['badge']))
                    <div class="mt-2"><x-badge :variant="($item['variant'] ?? 'neutral')">{{ $item['badge'] }}</x-badge></div>
                @endif
            </li>
        @empty
            <li class="text-sm text-slate-500">No data.</li>
        @endforelse
    </ol>
</div>

