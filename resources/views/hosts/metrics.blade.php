@extends('layouts.app')

@section('content')
<div class="container mx-auto p-6 space-y-6">
  <x-panel class="p-6">
    <div class="flex justify-between items-center mb-4">
      <h2 class="text-2xl font-semibold text-gray-800 dark:text-white">
        📈 Metryki hosta: {{ $host->host_name }}
      </h2>
      <a href="{{ route('hosts.show', $host) }}"
         class="inline-flex px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded hover:bg-gray-300 dark:hover:bg-gray-600 transition">
        ← Powrót
      </a>
    </div>

    <form method="GET" class="flex flex-wrap items-center gap-4 mb-6">
      <div>
        <label for="from" class="text-sm text-gray-700 dark:text-gray-300">Od:</label>
        <input
          type="datetime-local"
          name="from"
          id="from"
          value="{{ $from->format('Y-m-d\TH:i') }}"
          class="px-2 py-1 rounded border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white text-sm"
        >
      </div>
      <div>
        <label for="to" class="text-sm text-gray-700 dark:text-gray-300">Do:</label>
        <input
          type="datetime-local"
          name="to"
          id="to"
          value="{{ $to->format('Y-m-d\TH:i') }}"
          class="px-2 py-1 rounded border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white text-sm"
        >
      </div>
      <button
        type="submit"
        class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded hover:bg-blue-700 transition"
      >
        🔄 Odśwież
      </button>
    </form>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
      @foreach ($chartData as $metric => $data)
        @php
          $slug     = \Illuminate\Support\Str::slug($metric);
          $canvasId = "chart-{$slug}";
        @endphp
        <x-panel class="p-4 bg-white dark:bg-gray-800 rounded shadow h-96 flex flex-col">
          <h3 class="text-base font-semibold text-gray-800 dark:text-white mb-2">
            {{ $metric }}@if(!empty($units[$metric])) ({{ $units[$metric] }})@endif
          </h3>
          <div class="flex-1 relative">
            <canvas id="{{ $canvasId }}" class="w-full h-full"></canvas>
          </div>
        </x-panel>
      @endforeach
    </div>
  </x-panel>
</div>
@endsection

@push('scripts')
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script>
  document.addEventListener('DOMContentLoaded', () => {
    // same defaults as show.blade.php
    Chart.defaults.color = '#ccc';
    Chart.defaults.font.family = 'Inter, sans-serif';

    // labels come straight from controller, already formatted
    const labels    = @json($labels);
    const chartData = @json($chartData);
    const units     = @json($units);

    Object.entries(chartData).forEach(([metric, data]) => {
      const slug   = metric
                      .toLowerCase()
                      .replace(/[^a-z0-9]+/g, '-')
                      .replace(/(^-|-$)/g, '');
      const canvas = document.getElementById(`chart-${slug}`);
      if (!canvas) return;

      // destroy existing chart if any
      const existing = Chart.getChart(canvas);
      if (existing) existing.destroy();

      const ctx = canvas.getContext('2d');

      // main values
      const values = data.series.map(p => p.value);
      // alert points aligned by index
      const alertValues = labels.map((_, i) => {
        const ts = data.series[i]?.timestamp;
        const alert = data.alertPoints.find(a => a.timestamp === ts);
        return alert ? alert.value : null;
      });

      new Chart(ctx, {
        type: 'line',
        data: {
          labels,
          datasets: [
            {
              label: metric + (units[metric] ? ` (${units[metric]})` : ''),
              data: values,
              borderColor: data.seriesColor || '#3B82F6',
              backgroundColor: data.seriesColor || '#3B82F6',
              fill: false,
              tension: 0.3,
              borderWidth: 2,
              pointRadius: 0,
              hoverRadius: 4
            },
            {
              label: 'Alerty',
              data: alertValues,
              type: 'scatter',
              pointStyle: 'crossRot',
              pointRadius: 6,
              backgroundColor: '#EF4444',
              borderColor: '#EF4444',
              showLine: false
            }
          ]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              position: 'top',
              labels: {
                usePointStyle: true,
                boxWidth: 8,
                padding: 16,
                color: '#fff'
              }
            },
            tooltip: {
              mode: 'index',
              intersect: false
            }
          },
          scales: {
            x: {
              display: true,
              title: { display: true, text: 'Czas', color: '#aaa' },
              ticks: { color: '#ccc', autoSkip: true, maxTicksLimit: 8 },
              grid: { color: '#2d3748' }
            },
            y: {
              display: true,
              title: { display: true, text: units[metric] || 'Wartość', color: '#aaa' },
              ticks: { color: '#ccc' },
              grid: { color: '#2d3748' }
            }
          },
          layout: { padding: { top: 10, bottom: 0 } }
        }
      });
    });
  });
  </script>
@endpush
