@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto p-6 space-y-6">

  <div class="flex justify-between items-center">
    <h1 class="text-2xl font-bold">👥 Użytkownicy</h1>
    <a href="{{ route('admin.users.create') }}"
       class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
      ➕ Dodaj użytkownika
    </a>
  </div>

  <x-panel class="overflow-x-auto p-0">
    <table class="w-full text-sm">
      <thead class="bg-gray-200 dark:bg-gray-700 uppercase text-xs">
        <tr>
          <th class="px-4 py-2">ID</th>
          <th class="px-4 py-2">Login</th>
          <th class="px-4 py-2">Email</th>
          <th class="px-4 py-2">Imię</th>
          <th class="px-4 py-2">Nazwisko</th>
          <th class="px-4 py-2">Rola</th>
          <th class="px-4 py-2">Status</th>
          <th class="px-4 py-2">Akcje</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
        @foreach($users as $u)
          <tr>
            <td class="px-4 py-2">{{ $u->id }}</td>
            <td class="px-4 py-2">{{ $u->login }}</td>
            <td class="px-4 py-2">{{ $u->email }}</td>
            <td class="px-4 py-2">{{ $u->first_name }}</td>
            <td class="px-4 py-2">{{ $u->last_name }}</td>
            <td class="px-4 py-2">{{ $u->role }}</td>
            <td class="px-4 py-2">
              @if($u->is_active)
                <span class="px-2 py-1 text-xs bg-green-200 text-green-800 rounded">Aktywny</span>
              @else
                <span class="px-2 py-1 text-xs bg-red-200 text-red-800 rounded">Zablokowany</span>
              @endif
            </td>
            <td class="px-4 py-2 space-x-2">
              <a href="{{ route('admin.users.edit', $u) }}"
                 class="text-blue-600 hover:underline text-xs">Edytuj</a>
              <form method="POST" action="{{ route('admin.users.destroy', $u) }}"
                    class="inline" onsubmit="return confirm('Usunąć użytkownika?')">
                @csrf @method('DELETE')
                <button type="submit" class="text-red-600 hover:underline text-xs">
                  Usuń
                </button>
              </form>
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </x-panel>

</div>
@endsection
