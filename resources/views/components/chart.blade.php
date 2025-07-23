@props(['id','height'=>200,'class'=>''])
<div {{ $attributes->merge(['class'=> "bg-white dark:bg-gray-800 p-4 rounded-xl shadow $class"]) }}>
  <canvas id="{{ $id }}" height="{{ $height }}" class="w-full"></canvas>
</div>
