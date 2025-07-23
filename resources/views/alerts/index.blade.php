@extends('layouts.app')

@section('content')
<div class="container mx-auto p-6">
  <h1 class="text-2xl font-bold mb-4 text-gray-900 dark:text-gray-100">📣 Ostatnie alerty</h1>

  @if ($alerts->isEmpty())
    <p class="text-gray-700 dark:text-gray-300">Brak zarejestrowanych alertów.</p>
  @else
    <div class="overflow-x-auto bg-white rounded shadow dark:bg-gray-800">
      <table class="min-w-full text-left divide-y divide-gray-200 dark:divide-gray-700">
        <thead class="bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200 uppercase text-sm">
          <tr>
            <th class="px-4 py-2">Data</th>
            <th class="px-4 py-2">Host</th>
            <th class="px-4 py-2">Typ</th>
            <th class="px-4 py-2">Bieżąca wartość</th>
            <th class="px-4 py-2">Próg</th>
            <th class="px-4 py-2">Akcje</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($alerts as $alert)
            <tr class="border-b border-gray-200 dark:border-gray-700">
              <td class="px-4 py-2">{{ $alert->created_at->format('Y-m-d H:i') }}</td>
              <td class="px-4 py-2">{{ $alert->host->host_name }}</td>
              <td class="px-4 py-2">{{ $alert->metricType->metric_name }}</td>
              <td class="px-4 py-2">
                {{ $alert->current_value }} {{ $alert->metricType->unit }}
              </td>
              <td class="px-4 py-2">
                {{ $alert->threshold_value }} {{ $alert->metricType->unit }}
              </td>
              <td class="px-4 py-2 space-x-2">
                @if ($alert->status === 'Active')
                  <form action="{{ route('alerts.acknowledge', $alert) }}" method="POST" class="inline">
                    @csrf
                    <button type="submit"
                            class="px-2 py-1 bg-yellow-200 text-yellow-800 rounded text-xs hover:bg-yellow-300"
                            onclick="return confirm('Potwierdzić alert?')">
                      ✅ Potwierdź
                    </button>
                  </form>
                  <form action="{{ route('alerts.close', $alert) }}" method="POST" class="inline">
                    @csrf
                    <button type="submit"
                            class="px-2 py-1 bg-red-200 text-red-800 rounded text-xs hover:bg-red-300"
                            onclick="return confirm('Zamknąć alert?')">
                      ❌ Zamknij
                    </button>
                  </form>
                @else
                  <span class="text-gray-500 text-xs">{{ $alert->status }}</span>
                @endif

                <a href="{{ route('hosts.show', $alert->host_id) }}"
                   class="text-blue-600 hover:underline text-xs ml-1">
                  Zobacz hosta
                </a>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</div>
@endsection
