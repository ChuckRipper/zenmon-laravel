@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto p-6 space-y-8">

  <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">🧩 Typy metryk</h1>

  <div class="flex justify-end">
    <a href="{{ route('admin.metric-types.create') }}"
       class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
      ➕ Dodaj typ metryki
    </a>
  </div>

  <div class="overflow-x-auto bg-white dark:bg-gray-800 rounded shadow">
    <table class="w-full text-sm text-left text-gray-900 dark:text-gray-100">
      <thead class="bg-gray-200 dark:bg-gray-700 uppercase text-xs">
        <tr>
          <th class="px-3 py-2">ID</th>
          <th class="px-3 py-2">Nazwa</th>
          <th class="px-3 py-2">Jednostka</th>
          <th class="px-3 py-2">Opis</th>
          <th class="px-3 py-2">Akcje</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-200 dark:divide-gray-600">
        @foreach($types as $type)
          <tr>
            <td class="px-3 py-2">{{ $type->metric_type_id }}</td>
            <td class="px-3 py-2">{{ $type->metric_name }}</td>
            <td class="px-3 py-2">{{ $type->unit }}</td>
            <td class="px-3 py-2">{{ $type->description }}</td>
            <td class="px-3 py-2 space-x-2">
              <a href="{{ route('admin.metric-types.edit', $type) }}"
                 class="text-blue-600 hover:underline text-xs">Edytuj</a>
              <form action="{{ route('admin.metric-types.destroy', $type) }}"
                    method="POST" class="inline">
                @csrf @method('DELETE')
                <button type="submit"
                        class="text-red-600 hover:underline text-xs"
                        onclick="return confirm('Usunąć ten typ metryki?')">
                  Usuń
                </button>
              </form>
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
  <div class="mt-4">
  {{-- Paginacja wyśrodkowana --}}
  <div class="flex justify-center">
    {{ $types->links('pagination::tailwind') }}
  </div>

  {{-- Tekst pod paginacją --}}
  <!-- <p class="mt-2 text-sm text-gray-600 dark:text-gray-400 text-center">
    Pokazywanie {{ $types->firstItem() }} – {{ $types->lastItem() }} z {{ $types->total() }} wyników
  </p> -->
</div>

</div>
@endsection
