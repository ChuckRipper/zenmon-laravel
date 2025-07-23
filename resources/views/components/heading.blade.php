@props([
    'level' => 2,
    'class' => ''
])

@php
    $tag = 'h' . $level;
@endphp

<{{ $tag }} {{ $attributes->merge([
    'class' => "text-gray-900 dark:text-gray-100 font-semibold tracking-tight leading-tight " .
               match((int)$level) {
                   1 => 'text-3xl',
                   2 => 'text-2xl',
                   3 => 'text-xl',
                   4 => 'text-lg',
                   default => 'text-base'
               } . ' ' . $class
]) }}>
    {{ $slot }}
</{{ $tag }}>
