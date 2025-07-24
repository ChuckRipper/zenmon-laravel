@extends('layouts.app')

@section('content')
<div class="p-6 bg-white dark:bg-gray-900 rounded shadow space-y-8">
    <h2 class="text-2xl font-semibold text-gray-800 dark:text-white">
      ⚙️ Konfiguracja hosta: {{ $host->host_name }}
    </h2>

    <!-- {{-- Sekcja: Ustawienia ogólne --}}
    <div>
        <h3 class="text-lg font-medium mb-2 text-gray-800 dark:text-white">🛠️ Ustawienia zbierania danych</h3>
        <form method="POST" action="{{ route('hosts.config.save', $host) }}">
            @csrf
            <div class="space-y-4">
                <div>
                    <label for="data_collection_interval" class="block text-sm font-medium mb-1 text-gray-800 dark:text-white">Interwał zbierania danych (sekundy)</label>
                    <input type="number" id="data_collection_interval" name="data_collection_interval"
                           class="w-full px-3 py-2 rounded border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                           value="{{ old('data_collection_interval', optional($host->configuration)->data_collection_interval) }}"
                           min="10" max="3600" required>
                </div>

                <div>
                    <label for="max_log_entries" class="block text-sm font-medium mb-1 text-gray-800 dark:text-white">Maksymalna liczba wpisów w logu</label>
                    <input type="number" id="max_log_entries" name="max_log_entries"
                           class="w-full px-3 py-2 rounded border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                           value="{{ old('max_log_entries', optional($host->configuration)->max_log_entries) }}"
                           min="100" max="100000" required>
                </div>

                <div class="flex items-center">
                    <input type="checkbox" id="email_notifications" name="email_notifications" value="1"
                           class="mr-2"
                           {{ old('email_notifications', optional($host->configuration)->email_notifications) ? 'checked' : '' }}>
                    <label for="email_notifications" class="font-medium text-gray-800 dark:text-white">Powiadomienia Email</label>
                </div>

                <div class="flex items-center">
                    <input type="checkbox" id="slack_notifications" name="slack_notifications" value="1"
                           class="mr-2"
                           {{ old('slack_notifications', optional($host->configuration)->slack_notifications) ? 'checked' : '' }}>
                    <label for="slack_notifications" class="font-medium text-gray-800 dark:text-white">Powiadomienia Slack</label>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">💾 Zapisz ustawienia</button>
                </div>
            </div>
        </form>
    </div> -->

    {{-- Sekcja: Monitorowane katalogi --}}
    <div>
        <h3 class="text-lg font-medium mb-2 text-gray-800 dark:text-white">📂 Monitorowane katalogi</h3>

        @if($host->monitoredDirectories->isEmpty())
            <p class="text-gray-600 dark:text-gray-400">Brak monitorowanych katalogów.</p>
        @else
            <ul class="space-y-2 mb-4">
                @foreach($host->monitoredDirectories as $dir)
                    <li class="flex justify-between items-center">
                        <span class="text-gray-800 dark:text-white">{{ $dir->directory_path }}</span>
                        <form method="POST" action="{{ route('directories.destroy', $dir) }}" onsubmit="return confirm('Czy na pewno usunąć katalog?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-600 hover:underline dark:text-red-400">Usuń</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif

        <form method="POST" action="{{ route('directories.store') }}">
            @csrf
            <input type="hidden" name="host_id" value="{{ $host->host_id }}">
            <div class="space-y-4">
                <div>
                    <label for="directory_path" class="block text-sm font-medium mb-1 text-gray-800 dark:text-white">Ścieżka katalogu</label>
                    <input type="text" id="directory_path" name="directory_path"
                           class="w-full px-3 py-2 rounded border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                           placeholder="/var/log/httpd" required>
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">➕ Dodaj katalog</button>
                </div>
            </div>
        </form>
    </div>

    {{-- Sekcja: Progi alertowe --}}
    <div>
        <h3 class="text-lg font-medium mb-2 text-gray-800 dark:text-white">🧠 Progi alertowe</h3>

        <form method="POST" action="{{ route('hosts.config.save', $host) }}">
          @csrf
          <div class="space-y-4">
            <div>
              <label for="metric_select" class="block text-sm font-medium mb-1 text-gray-800 dark:text-white">Wybierz metrykę</label>
              <select id="metric_select" name="metric_type_id"
                      class="mt-1 block w-full rounded border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                @foreach($metricTypes as $mt)
                  <option value="{{ $mt->metric_type_id }}">{{ $mt->metric_name }} ({{ $mt->unit }})</option>
                @endforeach
              </select>
            </div>

            <div>
              <label for="warning_threshold" class="block text-sm font-medium mb-1 text-gray-800 dark:text-white">Próg Warning</label>
              <input type="number" id="warning_threshold" name="warning_threshold"
                     class="w-full px-3 py-2 rounded border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white" />
            </div>

            <div>
              <label for="critical_threshold" class="block text-sm font-medium mb-1 text-gray-800 dark:text-white">Próg Critical</label>
              <input type="number" id="critical_threshold" name="critical_threshold"
                     class="w-full px-3 py-2 rounded border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white" />
            </div>

            <div class="flex justify-end">
              <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">💾 Zapisz progi</button>
            </div>
          </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
  const hostThresholds = @json(
    $hostThresholds->mapWithKeys(fn($thr) => [
      $thr->metric_type_id => [
        'warning'  => $thr->warning_threshold,
        'critical' => $thr->critical_threshold
      ]
    ])
  );

  function updateThresholdInputs() {
    const select = document.getElementById('metric_select');
    const selId  = select.value;
    const thr    = hostThresholds[selId] || { warning: '', critical: '' };
    document.getElementById('warning_threshold').value  = thr.warning;
    document.getElementById('critical_threshold').value = thr.critical;
  }

  document.getElementById('metric_select')
          .addEventListener('change', updateThresholdInputs);

  updateThresholdInputs();
</script>
@endpush

@endsection
