@extends('layouts.app')

@section('content')
<div class="container mx-auto p-6">
  <h1 class="text-2xl font-bold mb-4 text-gray-900 dark:text-gray-100">Lista hostów</h1>

  {{-- FORMULARZ WYSZUKIWANIA --}}
  <form method="GET" action="{{ route('hosts.index') }}" class="mb-4 flex space-x-2">
    <input
      type="text"
      name="q"
      value="{{ old('q', request('q')) }}"
      placeholder="Szukaj po nazwie lub IP"
      class="flex-1 border rounded px-3 py-2 
           !text-black bg-white 
           focus:outline-none focus:ring"
    >
    <button
      type="submit"
      class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
    >
      Szukaj
    </button>
    @if(request('q'))
      <a
        href="{{ route('hosts.index') }}"
        class="px-4 py-2 bg-gray-300 text-gray-800 rounded hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500"
      >
        Wyczyść
      </a>
    @endif
  </form>

  <div class="overflow-x-auto bg-white rounded shadow dark:bg-gray-800">
    <table class="min-w-full text-left">
      <thead class="bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200 uppercase text-sm">
        <tr>
          <th class="px-4 py-2">Nazwa</th>
          <th class="px-4 py-2">IP</th>
          <th class="px-4 py-2">System</th>
          <th class="px-4 py-2">Wersja agenta</th>
          <th class="px-4 py-2">Aktywny</th>
          <th class="px-4 py-2">Ostatni kontakt</th>
          <th class="px-4 py-2">Akcje</th>
        </tr>
      </thead>
      <tbody>
        @forelse($hosts as $host)
          <tr class="border-b border-gray-200 dark:border-gray-700">
            <td class="px-4 py-2">{{ $host->host_name }}</td>
            <td class="px-4 py-2">{{ $host->ip_address }}</td>
            <td class="px-4 py-2">{{ $host->operating_system }}</td>
            <td class="px-4 py-2">{{ $host->agent_version }}</td>
            <td class="px-4 py-2">
              <span class="inline-block px-2 py-1 text-xs font-semibold rounded-full
                {{ $host->is_active
                   ? 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100'
                   : 'bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100' }}">
                {{ $host->is_active ? 'Tak' : 'Nie' }}
              </span>
            </td>
            <td class="px-4 py-2">
              {{ optional($host->last_contact_date)->diffForHumans() ?? '–' }}
            </td>
            <td class="px-4 py-2 space-x-2">
              <a href="{{ route('hosts.show', $host->host_id) }}"
                 class="inline-block px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                Pokaż dane
              </a>
              <a href="{{ route('hosts.config', $host->host_id) }}"
                 class="inline-block px-3 py-1 bg-gray-700 text-white rounded hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-500">
                ⚙️ Konfiguruj
              </a>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="7" class="px-4 py-2 text-center text-gray-600 dark:text-gray-400">
              Brak hostów do wyświetlenia.
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  {{-- PAGINACJA --}}
  <div class="mt-4">
    {{ $hosts->links() }}
  </div>
</div>
@endsection
