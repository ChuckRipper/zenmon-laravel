@extends('layouts.app')

@section('content')
<div class="p-6 bg-white dark:bg-gray-900 rounded shadow space-y-8">
    <div class="flex items-center justify-between">
        <h2 class="text-2xl font-semibold text-gray-800 dark:text-white">
            ⚙️ Konfiguracja hosta: {{ $host->host_name }}
        </h2>
        <a href="{{ route('hosts.index') }}" 
           class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 text-sm">
            ← Powrót do listy hostów
        </a>
    </div>

    @if(session('success'))
        <div class="p-4 bg-green-200 text-green-800 rounded-md">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="p-4 bg-red-200 text-red-800 rounded-md">
            {{ session('error') }}
        </div>
    @endif

    {{-- Informacje o systemie operacyjnym --}}
    @if($host->operating_system && str_contains(strtolower($host->operating_system), 'windows'))
        <div class="p-4 bg-blue-100 border border-blue-300 rounded-md">
            <h4 class="text-md font-medium text-blue-800 mb-2">💻 System Windows wykryty</h4>
            <p class="text-sm text-blue-700">
                Na hostach Windows automatycznie monitorowane są wszystkie dyski systemowe. 
                Dodawanie niestandardowych katalogów nie jest dostępne - system automatycznie skanuje dyski C:, D:, itp.
            </p>
        </div>
    @endif

    {{-- Sekcja: Ustawienia monitoringu --}}
    <div>
        <h3 class="text-lg font-medium mb-4 text-gray-800 dark:text-white">🛠️ Ustawienia monitoringu</h3>
        <form method="POST" action="{{ route('hosts.config.save', $host) }}">
            @csrf
            <div class="space-y-6">
                
                {{-- Interwał zbierania danych --}}
                <div>
                    <label for="data_collection_interval" class="block text-sm font-medium mb-2 text-gray-800 dark:text-white">
                        Interwał zbierania danych (sekundy)
                    </label>
                    <input type="number" 
                           name="data_collection_interval" 
                           id="data_collection_interval"
                           value="{{ old('data_collection_interval', optional($host->configuration)->data_collection_interval ?? 120) }}"
                           class="w-full px-3 py-2 rounded border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                           onblur="validateIntervalOnBlur(this)"
                           oninput="showLivePreview(this)"
                           required>
                    <p class="text-xs text-gray-500 mt-1">
                        Zakres: 30-600 sekund. Przy błędnej wartości zostanie ustawione 120s (domyślnie).
                    </p>
                    <div id="interval-preview" class="text-xs mt-1 h-4"></div>
                    @error('data_collection_interval')
                        <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Typy monitoringu --}}
                <div>
                    <h4 class="text-md font-medium mb-3 text-gray-800 dark:text-white">Monitorowane zasoby:</h4>
                    <div class="space-y-3">
                        
                        {{-- CPU Monitoring --}}
                        <div class="flex items-center">
                            <input type="hidden" name="enable_cpu_monitoring" value="0">
                            <input type="checkbox" 
                                   name="enable_cpu_monitoring" 
                                   id="enable_cpu_monitoring"
                                   value="1"
                                   {{ old('enable_cpu_monitoring', optional($host->configuration)->enable_cpu_monitoring ?? true) ? 'checked' : '' }}
                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded mr-3">
                            <label for="enable_cpu_monitoring" class="text-sm text-gray-700 dark:text-gray-300">
                                🖥️ <strong>Monitorowanie CPU</strong> - wykorzystanie procesora w procentach
                            </label>
                        </div>

                        {{-- RAM Monitoring --}}
                        <div class="flex items-center">
                            <input type="hidden" name="enable_ram_monitoring" value="0">
                            <input type="checkbox" 
                                   name="enable_ram_monitoring" 
                                   id="enable_ram_monitoring"
                                   value="1"
                                   {{ old('enable_ram_monitoring', optional($host->configuration)->enable_ram_monitoring ?? true) ? 'checked' : '' }}
                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded mr-3">
                            <label for="enable_ram_monitoring" class="text-sm text-gray-700 dark:text-gray-300">
                                🧠 <strong>Monitorowanie RAM</strong> - wykorzystanie pamięci operacyjnej
                            </label>
                        </div>

                        {{-- Disk Monitoring --}}
                        <div class="flex items-center">
                            <input type="hidden" name="enable_disk_monitoring" value="0">
                            <input type="checkbox" 
                                   name="enable_disk_monitoring" 
                                   id="enable_disk_monitoring"
                                   value="1"
                                   {{ old('enable_disk_monitoring', optional($host->configuration)->enable_disk_monitoring ?? true) ? 'checked' : '' }}
                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded mr-3">
                            <label for="enable_disk_monitoring" class="text-sm text-gray-700 dark:text-gray-300">
                                💾 <strong>Monitorowanie dysku</strong> - wykorzystanie przestrzeni dyskowej
                            </label>
                        </div>

                        {{-- Network Monitoring --}}
                        <div class="flex items-center">
                            <input type="hidden" name="enable_network_monitoring" value="0">
                            <input type="checkbox" 
                                   name="enable_network_monitoring" 
                                   id="enable_network_monitoring"
                                   value="1"
                                   {{ old('enable_network_monitoring', optional($host->configuration)->enable_network_monitoring ?? true) ? 'checked' : '' }}
                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded mr-3">
                            <label for="enable_network_monitoring" class="text-sm text-gray-700 dark:text-gray-300">
                                🌐 <strong>Monitorowanie sieci</strong> - testy połączenia i ping
                            </label>
                        </div>
                    </div>
                </div>

                {{-- Przyciski akcji --}}
                <div class="flex justify-end space-x-3 pt-4 border-t border-gray-200 dark:border-gray-600">
                    <a href="{{ route('hosts.index') }}" 
                       class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600 text-sm">
                        Anuluj
                    </a>
                    <button type="submit" 
                            class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 text-sm">
                        💾 Zapisz konfigurację
                    </button>
                </div>
            </div>
        </form>
    </div>

    {{-- Sekcja: Monitorowane katalogi (tylko dla nie-Windows) --}}
    @if(!$host->operating_system || !str_contains(strtolower($host->operating_system), 'windows'))
    <div>
        <h3 class="text-lg font-medium mb-2 text-gray-800 dark:text-white">📂 Monitorowane katalogi</h3>

        @if($host->monitoredDirectories->isEmpty())
            <p class="text-gray-600 dark:text-gray-400">Brak monitorowanych katalogów.</p>
        @else
            <ul class="space-y-2 mb-4">
                @foreach($host->monitoredDirectories as $dir)
                    <li class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-700 rounded">
                        <span class="text-gray-800 dark:text-white">{{ $dir->directory_path }}</span>
                        <form method="POST" action="{{ route('directories.destroy', $dir) }}" onsubmit="return confirm('Czy na pewno usunąć katalog?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-600 hover:underline dark:text-red-400">🗑️ Usuń</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif

        <form method="POST" action="{{ route('directories.store') }}">
            @csrf
            <input type="hidden" name="host_id" value="{{ $host->host_id }}">
            <div class="flex gap-2">
                <input type="text" 
                       id="directory_path" 
                       name="directory_path"
                       class="flex-1 px-3 py-2 rounded border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                       placeholder="/var/log/httpd" 
                       required>
                <button type="submit" 
                        class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">
                    ➕ Dodaj katalog
                </button>
            </div>
            @error('directory_path')
                <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
            @enderror
        </form>
    </div>
    @else
    <div>
        <h3 class="text-lg font-medium mb-2 text-gray-800 dark:text-white">📂 Monitorowane katalogi</h3>
        <div class="p-4 bg-gray-100 border border-gray-300 rounded-md">
            <p class="text-sm text-gray-600">
                ⚠️ Na systemach Windows monitorowanie katalogów jest automatyczne. 
                Wszystkie dyski (C:, D:, E:, itp.) są automatycznie skanowane przez agenta.
            </p>
        </div>
    </div>
    @endif

    {{-- Sekcja: Progi alertowe --}}
    <div>
        <h3 class="text-lg font-medium mb-2 text-gray-800 dark:text-white">🚨 Progi alertowe</h3>

        <form method="POST" action="{{ route('hosts.config.save', $host) }}">
          @csrf
          <input type="hidden" name="action" value="save_threshold">
          <input type="hidden" name="host_id" value="{{ $host->host_id }}">
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
              <input type="number" step="0.01" id="warning_threshold" name="warning_threshold"
                     class="w-full px-3 py-2 rounded border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white" />
            </div>

            <div>
              <label for="critical_threshold" class="block text-sm font-medium mb-1 text-gray-800 dark:text-white">Próg Critical</label>
              <input type="number" step="0.01" id="critical_threshold" name="critical_threshold"
                     class="w-full px-3 py-2 rounded border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white" />
            </div>

            <div class="flex justify-end">
              <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">💾 Zapisz progi</button>
            </div>
          </div>
        </form>

        {{-- Lista istniejących progów --}}
        @if($hostThresholds && $hostThresholds->isNotEmpty())
        <div class="mt-6">
            <h4 class="text-md font-medium mb-3 text-gray-800 dark:text-white">📋 Aktualne progi</h4>
            <div class="space-y-2">
                @foreach($hostThresholds as $threshold)
                <div class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-700 rounded">
                    <span class="text-gray-800 dark:text-white">
                        <strong>{{ $threshold->metricType->metric_name }}</strong>:
                        Warning: {{ $threshold->warning_threshold }}{{ $threshold->metricType->unit }},
                        Critical: {{ $threshold->critical_threshold }}{{ $threshold->metricType->unit }}
                    </span>
                    <form method="POST" action="{{ route('hosts.config.save', $host) }}" 
                          onsubmit="return confirm('Czy na pewno usunąć próg?');" class="inline">
                        @csrf
                        <input type="hidden" name="action" value="delete_threshold">
                        <input type="hidden" name="threshold_id" value="{{ $threshold->threshold_id }}">
                        <button type="submit" class="text-red-600 hover:underline dark:text-red-400">🗑️ Usuń</button>
                    </form>
                </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>

    {{-- Informacje o aktualnej konfiguracji --}}
    @if($host->configuration)
    <div class="bg-gray-50 dark:bg-gray-800 p-4 rounded">
        <h4 class="text-md font-medium mb-2 text-gray-800 dark:text-white">📋 Aktualna konfiguracja</h4>
        <div class="text-sm text-gray-600 dark:text-gray-400">
            <p><strong>Interwał:</strong> {{ $host->configuration->data_collection_interval }}s</p>
            <p><strong>CPU:</strong> {{ $host->configuration->enable_cpu_monitoring ? '✅ Włączone' : '❌ Wyłączone' }}</p>
            <p><strong>RAM:</strong> {{ $host->configuration->enable_ram_monitoring ? '✅ Włączone' : '❌ Wyłączone' }}</p>
            <p><strong>Dysk:</strong> {{ $host->configuration->enable_disk_monitoring ? '✅ Włączone' : '❌ Wyłączone' }}</p>
            <p><strong>Sieć:</strong> {{ $host->configuration->enable_network_monitoring ? '✅ Włączone' : '❌ Wyłączone' }}</p>
            @if($host->configuration->updated_at)
                <p><strong>Ostatnia zmiana:</strong> {{ $host->configuration->updated_at->format('Y-m-d H:i:s') }}</p>
            @endif
        </div>
    </div>
    @endif
</div>

@push('scripts')
<script>
  // Walidacja interwału
  function validateIntervalOnBlur(input) {
    const rawValue = input.value.trim();
    
    if (rawValue === '') {
      input.value = 120;
      showMessage('Ustawiono domyślną wartość: 120s', 'info');
      return;
    }
    
    const numericValue = parseFloat(rawValue.replace(',', '.'));
    
    if (isNaN(numericValue)) {
      input.value = 120;
      showMessage('Nieprawidłowa wartość. Ustawiono domyślnie: 120s', 'error');
      return;
    }
    
    const roundedValue = Math.round(numericValue);
    let finalValue = roundedValue;
    let message = '';
    let type = 'success';
    
    if (roundedValue < 30) {
      finalValue = 120;
      message = 'Wartość za niska (min 30s). Ustawiono domyślnie: 120s';
      type = 'error';
    } else if (roundedValue > 600) {
      finalValue = 120;
      message = 'Wartość za wysoka (max 600s). Ustawiono domyślnie: 120s';
      type = 'error';
    } else {
      const minutes = (finalValue / 60).toFixed(1);
      message = `✓ Wartość OK: ${finalValue}s (${minutes} min)`;
      type = 'success';
    }
    
    input.value = finalValue;
    showMessage(message, type);
  }
  
  // Podgląd na żywo
  function showLivePreview(input) {
    const rawValue = input.value.trim();
    const previewDiv = document.getElementById('interval-preview');
    
    if (rawValue === '') {
      previewDiv.innerHTML = '';
      return;
    }
    
    const numericValue = parseFloat(rawValue.replace(',', '.'));
    
    if (isNaN(numericValue)) {
      previewDiv.innerHTML = '<span class="text-gray-400">🔢 Wprowadź liczbę</span>';
      return;
    }
    
    const rounded = Math.round(numericValue);
    const minutes = (rounded / 60).toFixed(1);
    
    let status = '';
    let colorClass = '';
    
    if (rounded < 30) {
      status = '⚠️ Za niskie (min 30s)';
      colorClass = 'text-yellow-600';
    } else if (rounded > 600) {
      status = '⚠️ Za wysokie (max 600s)';
      colorClass = 'text-yellow-600';
    } else {
      status = `✓ OK (${minutes} min)`;
      colorClass = 'text-green-600';
    }
    
    previewDiv.innerHTML = `<span class="${colorClass}">${status}</span>`;
  }
  
  function showMessage(message, type = 'info') {
    const previewDiv = document.getElementById('interval-preview');
    
    let colorClass = '';
    switch(type) {
      case 'success':
        colorClass = 'text-green-600';
        break;
      case 'error':
        colorClass = 'text-red-600';
        break;
      case 'info':
        colorClass = 'text-blue-600';
        break;
      default:
        colorClass = 'text-gray-600';
    }
    
    previewDiv.innerHTML = `<span class="${colorClass}">${message}</span>`;
    
    setTimeout(() => {
      previewDiv.innerHTML = '';
    }, 4000);
  }
  
  // Progi alertowe - wczytaj istniejące wartości z bazy
  const hostThresholds = @json(
    isset($hostThresholds) ? $hostThresholds->mapWithKeys(fn($thr) => [
      $thr->metric_type_id => [
        'warning'  => $thr->warning_threshold,
        'critical' => $thr->critical_threshold
      ]
    ]) : []
  );

  function updateThresholdInputs() {
    const select = document.getElementById('metric_select');
    const selId  = select.value;
    const thr    = hostThresholds[selId] || { warning: '', critical: '' };
    document.getElementById('warning_threshold').value  = thr.warning;
    document.getElementById('critical_threshold').value = thr.critical;
  }

  // Wczytaj wartości przy ładowaniu strony i przy zmianie selecta
  document.getElementById('metric_select')
          .addEventListener('change', updateThresholdInputs);

  // Wczytaj przy starcie strony
  updateThresholdInputs();
</script>
@endpush

@endsection