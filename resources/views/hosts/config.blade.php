@extends('layouts.app')

@section('content')
<div class="p-6 bg-white dark:bg-gray-900 rounded shadow space-y-8">
    <h2 class="text-2xl font-semibold text-gray-800 dark:text-white">
      ⚙️ Konfiguracja hosta: {{ $host->host_name }}
    </h2>

    {{-- ... sekcja Informacje o hoście ... --}}

    {{-- Sekcja: Progi alertowe --}}
    <div>
        <h3 class="text-lg font-medium mb-2 text-gray-800 dark:text-white">
          🧠 Progi alertowe
        </h3>

        <form method="POST" action="{{ route('hosts.config.save', $host) }}">
          @csrf

          <div class="space-y-4">
            <div>
              <label for="metric_select" class="block text-sm font-medium mb-1">
                Wybierz metrykę
              </label>
              <select id="metric_select" name="metric_type_id"
                      class="mt-1 block w-full !text-black border-gray-300 rounded">
                @foreach($metricTypes as $mt)
                  <option value="{{ $mt->metric_type_id }}">
                    {{ $mt->metric_name }} ({{ $mt->unit }})
                  </option>
                @endforeach
              </select>
            </div>

            <div>
              <label for="warning_threshold" class="block text-sm font-medium mb-1">
                Próg Warning
              </label>
              <input type="number" id="warning_threshold" name="warning_threshold"
                     class="w-full px-3 py-2 rounded border border-gray-300 dark:border-gray-600
                            dark:bg-gray-800 dark:text-white" />
            </div>

            <div>
              <label for="critical_threshold" class="block text-sm font-medium mb-1">
                Próg Critical
              </label>
              <input type="number" id="critical_threshold" name="critical_threshold"
                     class="w-full px-3 py-2 rounded border border-gray-300 dark:border-gray-600
                            dark:bg-gray-800 dark:text-white" />
            </div>

            <div class="flex justify-end">
              <button type="submit"
                      class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">
                💾 Zapisz progi
              </button>
            </div>
          </div>
        </form>
    </div>

    {{-- ... dalsze sekcje widoku ... --}}
</div>

@push('scripts')
<script>
  // Zamieniamy $hostThresholds na prosty obiekt JS: { metric_type_id: { warning, critical }, ... }
  const hostThresholds = @json(
    $hostThresholds->mapWithKeys(function($thr) {
      return [
        $thr->metric_type_id => [
          'warning'  => $thr->warning_threshold,
          'critical' => $thr->critical_threshold
        ]
      ];
    })
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

  // Ustawimy wartości przy pierwszym ładowaniu strony
  updateThresholdInputs();
</script>
@endpush

@endsection
