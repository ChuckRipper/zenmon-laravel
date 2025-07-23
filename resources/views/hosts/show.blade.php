@extends('layouts.app')

@section('content')
<div class="container mx-auto p-6 space-y-6">

  {{-- GŁÓWNY PANEL Z INFORMACJAMI O HOŚCIE --}}
  <x-panel class="p-6">
    <div class="flex justify-between items-center">
      <div>
        <h2 class="text-2xl font-semibold text-gray-800 dark:text-white mb-1">
          🖥 {{ $host->host_name }}
        </h2>
        <p class="text-sm text-gray-500 dark:text-gray-400">
          Szczegóły hosta i jego połączenia
        </p>
      </div>

      {{-- Badge ze statusem połączenia --}}
      @php
        $lastCs = $host->connectionStatuses
                       ->sortByDesc('last_check_date')
                       ->first();
      @endphp
      <span class="inline-block px-3 py-1 rounded text-sm font-medium
        {{ ($lastCs && $lastCs->status==='Online')
            ? 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100'
            : 'bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100' }}">
        {{ $lastCs->status ?? 'Brak danych' }}
      </span>
    </div>

    <div class="overflow-x-auto mt-4">
      <table class="min-w-full table-auto text-sm bg-white dark:bg-gray-800 rounded shadow-sm">
        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
          <tr>
            <td class="px-4 py-2 font-semibold text-gray-600 dark:text-gray-300">Adres IP</td>
            <td class="px-4 py-2 text-gray-800 dark:text-gray-100">{{ $host->ip_address }}</td>
          </tr>
          <tr>
            <td class="px-4 py-2 font-semibold text-gray-600 dark:text-gray-300">OS</td>
            <td class="px-4 py-2 text-gray-800 dark:text-gray-100">{{ $host->operating_system ?? '—' }}</td>
          </tr>
          <tr>
            <td class="px-4 py-2 font-semibold text-gray-600 dark:text-gray-300">Agent v.</td>
            <td class="px-4 py-2 text-gray-800 dark:text-gray-100">{{ $host->agent_version ?? '—' }}</td>
          </tr>
          <tr>
            <td class="px-4 py-2 font-semibold text-gray-600 dark:text-gray-300">Ostatni kontakt</td>
            <td class="px-4 py-2 text-gray-800 dark:text-gray-100">
              {{ $host->last_contact_date
                   ? \Carbon\Carbon::parse($host->last_contact_date)->diffForHumans()
                   : '—' }}
            </td>
          </tr>
          <tr>
            <td class="px-4 py-2 font-semibold text-gray-600 dark:text-gray-300">Opis</td>
            <td class="px-4 py-2 text-gray-800 dark:text-gray-100">{{ $host->description ?? '—' }}</td>
          </tr>
        </tbody>
      </table>

      <div class="flex items-center justify-between mt-6">
        <a href="{{ route('hosts.index') }}"
           class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded hover:bg-gray-300">
          ← Lista hostów
        </a>
        <a href="{{ route('hosts.config', $host) }}"
           class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-gray-700 rounded hover:bg-gray-800">
          ⚙️ Konfiguruj
        </a>
      </div>
    </div>
  </x-panel>

  {{-- POMIARY Z OSTATNIEJ GODZINY – TABELA (max 10) --}}
  <x-panel class="p-6">
    <h3 class="text-lg font-semibold mb-4">Ostatnie pomiary (max 10)</h3>
    @if($recentMetrics->isEmpty())
      <p class="text-gray-500">Brak pomiarów w ostatniej godzinie.</p>
    @else
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200">
            <tr>
              <th class="px-4 py-2">Czas</th>
              <th class="px-4 py-2">Metryka</th>
              <th class="px-4 py-2">Wartość</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            @foreach($recentMetrics as $m)
              <tr>
                <td class="px-4 py-2">{{ $m->timestamp->format('H:i') }}</td>
                <td class="px-4 py-2">{{ $m->metricType->metric_name }}</td>
                <td class="px-4 py-2">
                  {{ rtrim(rtrim(number_format($m->value,4,',',''), '0'), ',') }}
                  {{ $m->metricType->unit }}
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </x-panel>

  {{-- WYKRES METRYK --}}
  <x-panel class="p-6">
    <h3 class="text-lg font-semibold mb-4">Wykres metryk (ostatnia godzina)</h3>
    <canvas id="metricsChart" height="200"></canvas>
    <div class="mt-4 text-right">
      <a href="{{ route('hosts.metrics', $host) }}"
         class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
        Szczegóły
      </a>
    </div>
  </x-panel>

  {{-- ALERTY HOSTA --}}
  <x-panel class="p-6">
    <h3 class="text-lg font-semibold mb-4">Alerty hosta</h3>
    @if($host->alerts->isEmpty())
      <p class="text-gray-500">Brak alertów.</p>
    @else
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200">
            <tr>
              <th class="px-4 py-2">Metryka</th>
              <th class="px-4 py-2">Poziom</th>
              <th class="px-4 py-2">Data</th>
              <th class="px-4 py-2">Wartość</th>
              <th class="px-4 py-2">Status</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            @foreach($host->alerts->sortByDesc('created_at') as $alert)
              <tr>
                <td class="px-4 py-2">{{ $alert->metricType->metric_name }}</td>
                <td class="px-4 py-2">{{ $alert->alert_level }}</td>
                <td class="px-4 py-2">{{ $alert->created_at->format('Y-m-d H:i') }}</td>
                <td class="px-4 py-2">
                  {{ rtrim(rtrim(number_format($alert->current_value,4,',',''), '0'), ',') }}
                  {{ $alert->metricType->unit }}
                </td>
                <td class="px-4 py-2">{{ $alert->status }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </x-panel>

</div>
@endsection

@push('scripts')
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const ctxContainer = document.getElementById('metricsChart').getContext('2d');
      let metricsChart = null;

      async function fetchAndRender() {
        try {
          const res = await fetch("{{ route('hosts.metrics-data', $host) }}");
          if (!res.ok) return;
          const { labels, datasets } = await res.json();

          // Dodajemy fill: false do każdego zestawu danych
          const wrapped = datasets.map(ds => ({ ...ds, fill: false }));

          if (!metricsChart) {
            metricsChart = new Chart(ctxContainer, {
              type: 'line',
              data: { labels, datasets: wrapped },
              options: {
                responsive: true,
                scales: {
                  x: { display: true, title: { display: true, text: 'Czas' } },
                  y: { display: true, title: { display: true, text: 'Wartość' } }
                }
              }
            });
          } else {
            metricsChart.data.labels   = labels;
            metricsChart.data.datasets = wrapped;
            metricsChart.update();
          }
        } catch (e) {
          console.error('Błąd przy pobieraniu danych do wykresu', e);
        }
      }

      // Pierwsze wywołanie i cykliczne odświeżanie co 2 minuty
      fetchAndRender();
      setInterval(fetchAndRender, 120_000);
    });
  </script>
@endpush
