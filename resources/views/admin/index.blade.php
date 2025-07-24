@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto p-6 space-y-8">

  {{-- Nagłówek --}}
  <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">👤 Panel administratora</h1>

  {{-- 1. Informacje o koncie --}}
  <x-panel class="p-6">
    <h2 class="text-lg font-semibold mb-4">🪪 Twoje konto</h2>
    <ul class="space-y-1 text-sm">
      <li><strong>Login:</strong> {{ auth()->user()->login }}</li>
      <li><strong>E-mail:</strong> {{ auth()->user()->email }}</li>
      <li><strong>Rola:</strong> {{ auth()->user()->role }}</li>
    </ul>
  </x-panel>

  {{-- 2. Statystyki systemowe --}}
  <x-panel class="p-6">
    <h2 class="text-lg font-semibold mb-4">📊 Statystyki</h2>
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
      <div class="bg-gray-100 dark:bg-gray-700 p-4 rounded">
        <p class="font-medium">Użytkownicy</p>
        <p class="text-2xl">{{ $totalUsers }}</p>
      </div>
      <div class="bg-gray-100 dark:bg-gray-700 p-4 rounded">
        <p class="font-medium">Aktywni</p>
        <p class="text-2xl">{{ $activeUsers }}</p>
      </div>
      <div class="bg-gray-100 dark:bg-gray-700 p-4 rounded">
        <p class="font-medium">Hosty</p>
        <p class="text-2xl">{{ $totalHosts }}</p>
      </div>
      <div class="bg-gray-100 dark:bg-gray-700 p-4 rounded">
        <p class="font-medium">Aktywne alerty</p>
        <p class="text-2xl">{{ $activeAlerts }}</p>
      </div>
    </div>
  </x-panel>

  {{-- 3. Zarządzanie użytkownikami --}}
  <x-panel class="p-6">
    <div class="flex justify-between items-center mb-4">
      <h2 class="text-lg font-semibold">👥 Użytkownicy</h2>
      <a href="{{ route('admin.users.create') }}"
         class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
        ➕ Dodaj
      </a>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-gray-200 dark:bg-gray-700 uppercase text-xs">
          <tr>
            <th class="px-3 py-2">ID</th>
            <th class="px-3 py-2">Login</th>
            <th class="px-3 py-2">Email</th>
            <th class="px-3 py-2">Rola</th>
            <th class="px-3 py-2">Status</th>
            <th class="px-3 py-2">Akcje</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
          @foreach($users as $u)
            <tr>
              <td class="px-3 py-2">{{ $u->id }}</td>
              <td class="px-3 py-2">{{ $u->login }}</td>
              <td class="px-3 py-2">{{ $u->email }}</td>
              <td class="px-3 py-2">{{ $u->role }}</td>
              <td class="px-3 py-2">
                @if($u->is_active)
                  <span class="text-green-800 bg-green-200 px-2 py-1 rounded text-xs">Aktywny</span>
                @else
                  <span class="text-red-800 bg-red-200 px-2 py-1 rounded text-xs">Zablokowany</span>
                @endif
              </td>
              <td class="px-3 py-2 space-x-2">
                <a href="{{ route('admin.users.edit', $u) }}"
                   class="text-blue-600 hover:underline text-xs">Edytuj</a>
                <form method="POST" action="{{ route('admin.users.destroy', $u) }}" class="inline">
                  @csrf @method('DELETE')
                  <button type="submit"
                          class="text-red-600 hover:underline text-xs"
                          onclick="return confirm('Na pewno usunąć?')">
                    Usuń
                  </button>
                </form>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </x-panel>

  {{-- 4. Hosty --}}
  <x-panel class="p-6">
    <div class="flex justify-between items-center mb-4">
      <h2 class="text-lg font-semibold">🖥️ Hosty</h2>
      <a href="{{ route('hosts.create') }}"
         class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
        ➕ Dodaj
      </a>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-gray-200 dark:bg-gray-700 uppercase text-xs">
          <tr>
            <th class="px-3 py-2">ID</th>
            <th class="px-3 py-2">Nazwa</th>
            <th class="px-3 py-2">IP</th>
            <th class="px-3 py-2">Status</th>
            <th class="px-3 py-2">Akcje</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
          @foreach($hosts as $h)
            <tr>
              <td class="px-3 py-2">{{ $h->host_id }}</td>
              <td class="px-3 py-2">{{ $h->host_name }}</td>
              <td class="px-3 py-2">{{ $h->ip_address }}</td>
              <td class="px-3 py-2">
                @if($h->is_active)
                  <span class="text-green-800 bg-green-200 px-2 py-1 rounded text-xs">Aktywny</span>
                @else
                  <span class="text-red-800 bg-red-200 px-2 py-1 rounded text-xs">Wyłączony</span>
                @endif
              </td>
              <td class="px-3 py-2 space-x-2">
                <a href="{{ route('hosts.edit', $h) }}"
                   class="text-blue-600 hover:underline text-xs">Edytuj</a>
                <form method="POST" action="{{ route('hosts.destroy', $h) }}" class="inline">
                  @csrf @method('DELETE')
                  <button type="submit"
                          class="text-red-600 hover:underline text-xs"
                          onclick="return confirm('Usunąć hosta?')">
                    Usuń
                  </button>
                </form>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </x-panel>

  {{-- 5. Typy metryk --}}
  <x-panel class="p-6">
    <div class="flex justify-between items-center mb-4">
      <h2 class="text-lg font-semibold">🧩 Typy metryk</h2>
      <a href="{{ route('admin.metric-types.create') }}"
         class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
        ➕ Dodaj
      </a>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-gray-200 dark:bg-gray-700 uppercase text-xs">
          <tr>
            <th class="px-3 py-2">ID</th>
            <th class="px-3 py-2">Nazwa</th>
            <th class="px-3 py-2">Jednostka</th>
            <th class="px-3 py-2">Akcje</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
          @foreach($metricTypes as $mt)
            <tr>
              <td class="px-3 py-2">{{ $mt->metric_type_id }}</td>
              <td class="px-3 py-2">{{ $mt->metric_name }}</td>
              <td class="px-3 py-2">{{ $mt->unit }}</td>
              <td class="px-3 py-2 space-x-2">
                <a href="{{ route('admin.metric-types.edit', $mt) }}"
                   class="text-blue-600 hover:underline text-xs">Edytuj</a>
                <form method="POST" action="{{ route('admin.metric-types.destroy', $mt) }}" class="inline">
                  @csrf @method('DELETE')
                  <button type="submit"
                          class="text-red-600 hover:underline text-xs"
                          onclick="return confirm('Usunąć typ metryki?')">
                    Usuń
                  </button>
                </form>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </x-panel>

  {{-- 6. Najnowsze alerty --}}
  <x-panel class="p-6">
    <h2 class="text-lg font-semibold mb-4">🚨 Ostatnie alerty</h2>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-gray-200 dark:bg-gray-700 uppercase text-xs">
          <tr>
            <th class="px-3 py-2">ID</th>
            <th class="px-3 py-2">Host</th>
            <th class="px-3 py-2">Metryka</th>
            <th class="px-3 py-2">Wartość</th>
            <th class="px-3 py-2">Poziom</th>
            <th class="px-3 py-2">Kiedy</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
          @foreach($recentAlerts as $a)
            <tr>
              <td class="px-3 py-2">{{ $a->alert_id }}</td>
              <td class="px-3 py-2">{{ $a->host->host_name }}</td>
              <td class="px-3 py-2">{{ $a->metricType->metric_name }}</td>
              <td class="px-3 py-2">{{ $a->current_value }} {{ $a->metricType->unit }}</td>
              <td class="px-3 py-2">{{ $a->alert_level }}</td>
              <td class="px-3 py-2">{{ $a->created_at->diffForHumans() }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </x-panel>

  {{-- 7. Narzędzia import/eksport --}}
  <x-panel class="p-6">
    <h2 class="text-lg font-semibold mb-4">🛠 Narzędzia</h2>
    <div class="flex flex-wrap gap-4">
      <a href="{{ route('admin.tools.export.form') }}"
         class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
        📤 Eksportuj konfigurację
      </a>
      <!-- <a href="{{ route('admin.tools.import.form') }}"
         class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700">
        📥 Importuj konfigurację
      </a> -->
    </div>
  </x-panel>

</div>
@endsection
