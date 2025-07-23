@extends('layouts.app')

@section('content')
<div class="container mx-auto p-6 space-y-6">
  <x-panel class="p-6">
    <div class="flex justify-between items-center mb-4">
      <h2 class="text-2xl font-semibold text-gray-800 dark:text-white">
        📈 Metryki hosta: {{ $host->host_name }}
      </h2>
      <a href="{{ route('hosts.show', $host) }}"
         class="inline-flex px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300">
        ← Powrót
      </a>
    </div>

    <form method="GET" class="flex flex-wrap items-center gap-4 mb-6">
      <div>
        <label for="from" class="text-sm text-gray-700 dark:text-gray-300">Od:</label>
        <input type="datetime-local" name="from" id="from"
               value="{{ $from->format('Y-m-d\TH:i') }}"
               class="px-2 py-1 rounded border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white text-sm">
      </div>
      <div>
        <label for="to" class="text-sm text-gray-700 dark:text-gray-300">Do:</label>
        <input type="datetime-local" name="to" id="to"
               value="{{ $to->format('Y-m-d\TH:i') }}"
               class="px-2 py-1 rounded border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white text-sm">
      </div>
      <button type="submit"
              class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded hover:bg-blue-700 transition">
        🔄 Odśwież
      </button>
    </form>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      @foreach ($chartData as $metric => $data)
        @php
          $slug     = \Illuminate\Support\Str::slug($metric);
          $canvasId = "chart-{$slug}";
        @endphp
        <x-panel class="p-4 bg-white dark:bg-gray-800 rounded shadow h-72 flex flex-col">
          <h3 class="text-base font-semibold text-gray-800 dark:text-white mb-2">
            {{ $metric }} @if(!empty($units[$metric])) ({{ $units[$metric] }}) @endif
          </h3>
          <div class="flex-1">
            <canvas id="{{ $canvasId }}" class="w-full h-full"></canvas>
          </div>
        </x-panel>
      @endforeach
    </div>
  </x-panel>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  console.log('👉 start chart init', { labels: @json($labels), chartData: @json($chartData), units: @json($units) });

  const labels    = @json($labels);
  const chartData = @json($chartData);
  const units     = @json($units);

  Object.keys(chartData).forEach(metric => {
    const slug = metric
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/(^-|-$)/g, '');
    const canvas = document.getElementById(`chart-${slug}`);
    console.log('looking for canvas', `chart-${slug}`, canvas);
    if (!canvas) return;
    const ctx = canvas.getContext('2d');

    new Chart(ctx, {
      type: 'line',
      data: {
        labels,
        datasets: [
          {
            label: metric + (units[metric] ? ` (${units[metric]})` : ''),
            data: chartData[metric].series.map(p => p.value),
            fill: false,
            tension: 0.4,
            pointRadius: 2
          },
          {
            label: 'Alerty',
            data: chartData[metric].alertPoints.map(p => p.value),
            type: 'scatter',
            pointStyle: 'cross',
            pointRadius: 6
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'top' } },
        scales: {
          x: { display: true, title: { display: true, text: 'Czas' } },
          y: { display: true, title: { display: true, text: units[metric] || 'Wartość' } }
        }
      }
    });
  });
});
</script>
@endpush