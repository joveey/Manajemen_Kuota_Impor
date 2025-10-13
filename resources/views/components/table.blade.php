@props([
    'zebra' => false,
    'class' => ''
])
<div {{ $attributes->merge(['class' => 'overflow-hidden rounded-2xl bg-white ring-1 ring-slate-200 '.$class]) }}>
    <table class="min-w-full text-sm">
        <thead class="bg-slate-50 text-slate-600">
            <tr>
                {{ $head }}
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-200 {{ $zebra ? 'odd:bg-white even:bg-slate-50/60' : '' }}">
            {{ $body }}
        </tbody>
    </table>
</div>

