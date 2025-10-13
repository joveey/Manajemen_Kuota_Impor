@props([
    'class' => ''
])
<div {{ $attributes->merge(['class' => 'bg-white rounded-2xl shadow-sm hover:shadow-md transition-shadow p-5 md:p-6 '.$class]) }}>
    {{ $slot }}
</div>

