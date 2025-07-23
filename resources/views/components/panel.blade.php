@props(['class' => ''])

<div {{ $attributes->merge(['class' => "bg-white dark:bg-gray-900 rounded shadow-sm ".$class]) }}>
    {{ $slot }}
</div>
