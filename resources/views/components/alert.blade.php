@props([
    'type' => 'info', // info | success | warning | danger
])

@php
    $colors = match($type) {
        'success' => 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100',
        'warning' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100',
        'danger'  => 'bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100',
        default   => 'bg-blue-100 text-blue-800 dark:bg-blue-800 dark:text-blue-100',
    };
@endphp

<div {{ $attributes->merge(['class' => "px-4 py-3 rounded-md text-sm font-medium ".$colors]) }}>
    {{ $slot }}
</div>
