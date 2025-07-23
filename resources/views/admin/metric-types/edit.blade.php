@extends('layouts.app')

@section('content')
<div class="max-w-md mx-auto p-6 space-y-6">

  <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">✏️ Edytuj typ metryki</h1>

  <div class="bg-white dark:bg-gray-800 rounded shadow p-6">
    <form method="POST" action="{{ route('admin.metric-types.update', $metricType) }}">
      @csrf @method('PUT')

      <div class="space-y-4">

        {{-- Nazwa metryki --}}
        <div>
          <label for="metric_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
            Nazwa metryki
          </label>
          <input id="metric_name" name="metric_name" type="text"
                 value="{{ old('metric_name', $metricType->metric_name) }}"
                 class="mt-1 block w-full border-gray-300 dark:border-gray-600 rounded p-2
                        bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
          @error('metric_name')
            <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
          @enderror
        </div>

        {{-- Jednostka --}}
        <div>
          <label for="unit" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
            Jednostka
          </label>
          <input id="unit" name="unit" type="text"
                 value="{{ old('unit', $metricType->unit) }}"
                 class="mt-1 block w-full border-gray-300 dark:border-gray-600 rounded p-2
                        bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
          @error('unit')
            <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
          @enderror
        </div>

        {{-- Opis --}}
        <div>
          <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
            Opis (opcjonalnie)
          </label>
          <textarea id="description" name="description" rows="3"
                    class="mt-1 block w-full border-gray-300 dark:border-gray-600 rounded p-2
                           bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">{{ old('description', $metricType->description) }}</textarea>
          @error('description')
            <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
          @enderror
        </div>

        {{-- Przyciski --}}
        <div class="flex justify-end space-x-2">
          <a href="{{ route('admin.metric-types.index') }}"
             class="px-4 py-2 bg-gray-200 !text-black rounded hover:bg-gray-300 text-sm">
            Anuluj
          </a>
          <button type="submit"
                  class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 text-sm">
            Zapisz
          </button>
        </div>

      </div>
    </form>
  </div>
</div>
@endsection
