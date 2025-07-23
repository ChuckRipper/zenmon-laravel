@extends('layouts.app')

@section('content')
    <x-panel class="p-6">
        <x-heading level="2" class="mb-4">📊 Dashboard</x-heading>

        {{-- Karty statystyk --}}
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-6 gap-4 mb-6">
            {{-- Hosty --}}
            <x-panel class="p-4 text-center">
                <h3 class="text-sm text-gray-500 dark:text-gray-400">Łączna liczba hostów</h3>
                <p class="text-2xl font-semibold text-blue-600 dark:text-blue-400">{{ $totalHosts }}</p>
            </x-panel>
            <x-panel class="p-4 text-center">
                <h3 class="text-sm text-gray-500 dark:text-gray-400">Aktywne hosty</h3>
                <p class="text-2xl font-semibold text-green-600 dark:text-green-400">{{ $activeHosts }}</p>
            </x-panel>
            <x-panel class="p-4 text-center">
                <h3 class="text-sm text-gray-500 dark:text-gray-400">Hosty z alertami</h3>
                <p class="text-2xl font-semibold text-red-600 dark:text-red-400">{{ $hostsWithAlerts }}</p>
            </x-panel>
            <x-panel class="p-4 text-center">
                <h3 class="text-sm text-gray-500 dark:text-gray-400">Hosty online</h3>
                <p class="text-2xl font-semibold text-emerald-600 dark:text-emerald-400">{{ $hostsOnline }}</p>
            </x-panel>

            {{-- Użytkownicy --}}
            <x-panel class="p-4 text-center">
                <h3 class="text-sm text-gray-500 dark:text-gray-400">Wszyscy użytkownicy</h3>
                <p class="text-2xl font-semibold text-indigo-600 dark:text-indigo-400">{{ $totalUsers }}</p>
            </x-panel>
            <x-panel class="p-4 text-center">
                <h3 class="text-sm text-gray-500 dark:text-gray-400">Aktywni użytkownicy</h3>
                <p class="text-2xl font-semibold text-green-600 dark:text-green-400">{{ $activeUsers }}</p>
            </x-panel>
        </div>

        <x-heading level="3" class="mb-4">Ostatnie alerty</x-heading>

        @if($recentAlerts->isEmpty())
            <p class="text-gray-500 dark:text-gray-400">Brak aktywnych alertów.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full table-auto bg-white dark:bg-gray-800 rounded shadow">
                    <thead>
                        <tr class="text-left text-sm font-semibold text-gray-600 dark:text-gray-300 border-b border-gray-200 dark:border-gray-700">
                            <th class="px-4 py-2">Host</th>
                            <th class="px-4 py-2">Typ metryki</th>
                            <th class="px-4 py-2">Poziom</th>
                            <th class="px-4 py-2">Data</th>
                            <th class="px-4 py-2">Wartość</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentAlerts as $alert)
                            <tr class="text-sm border-b border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="px-4 py-2">{{ $alert->host->host_name }}</td>
                                <td class="px-4 py-2">{{ $alert->metricType->metric_name }}</td>
                                <td class="px-4 py-2">
                                    <span class="inline-block px-2 py-1 rounded text-xs font-semibold
                                        {{ $alert->alert_level === 'Critical'
                                           ? 'bg-red-100 text-red-700 dark:bg-red-800 dark:text-red-200'
                                           : 'bg-yellow-100 text-yellow-700 dark:bg-yellow-800 dark:text-yellow-200' }}">
                                        {{ $alert->alert_level }}
                                    </span>
                                </td>
                                <td class="px-4 py-2">{{ $alert->created_at->format('Y-m-d H:i') }}</td>
                                <td class="px-4 py-2">{{ $alert->current_value }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-panel>
@endsection
