@extends('layouts.app')

@section('content')
<div class="max-w-md mx-auto p-6 space-y-6">

  <h1 class="text-2xl font-bold">✏️ Edytuj użytkownika</h1>

  <x-panel class="p-6">
    <form method="POST" action="{{ route('admin.users.update', $user) }}">
      @csrf
      @method('PUT')

      <div class="space-y-4">
        {{-- Login --}}
        <div>
          <label for="login" class="block text-sm font-medium text-white">Login</label>
          <input
            type="text"
            name="login"
            id="login"
            value="{{ old('login', $user->login) }}"
            class="mt-1 block w-full !text-black rounded border-gray-300 shadow-sm focus:ring focus:ring-indigo-200"
          />
          @error('login') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        {{-- E-mail --}}
        <div>
          <label for="email" class="block text-sm font-medium text-white">E-mail</label>
          <input
            type="email"
            name="email"
            id="email"
            value="{{ old('email', $user->email) }}"
            class="mt-1 block w-full rounded !text-black border-gray-300 shadow-sm focus:ring focus:ring-indigo-200"
          />
          @error('email') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        {{-- Imię --}}
        <div>
          <label for="first_name" class="block text-sm font-medium text-white">Imię</label>
          <input
            type="text"
            name="first_name"
            id="first_name"
            value="{{ old('first_name', $user->first_name) }}"
            class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:ring !text-black focus:ring-indigo-200"
          />
          @error('first_name') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        {{-- Nazwisko --}}
        <div>
          <label for="last_name" class="block text-sm font-medium text-white">Nazwisko</label>
          <input
            type="text"
            name="last_name"
            id="last_name"
            value="{{ old('last_name', $user->last_name) }}"
            class="mt-1 block w-full !text-black rounded border-gray-300 shadow-sm focus:ring focus:ring-indigo-200"
          />
          @error('last_name') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        {{-- Rola --}}
        <div>
          <label for="role" class="block text-sm font-medium text-white">Rola</label>
          <select
            name="role"
            id="role"
            class="mt-1 block w-full rounded !text-black border-gray-300 shadow-sm focus:ring focus:ring-indigo-200"
          >
            <option value="Administrator" @selected(old('role',$user->role)=='Administrator')>
              Administrator
            </option>
            <option value="User" @selected(old('role',$user->role)=='User')>
              Użytkownik
            </option>
          </select>
          @error('role') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        {{-- Aktywny --}}
        <div class="flex items-center space-x-2">
          <input type="hidden" name="is_active" value="0" />
          <input
            type="checkbox"
            name="is_active"
            id="is_active"
            value="1"
            @checked(old('is_active', $user->is_active))
            class="h-4 w-4 text-indigo-600 border-gray-300 rounded"
          />
          <label for="is_active" class="text-sm text-white">Aktywny</label>
        </div>

        {{-- Nowe hasło --}}
        <div>
          <label for="password" class="block text-sm font-medium text-white">
            Nowe hasło (opcjonalnie)
          </label>
          <input
            type="password"
            name="password"
            id="password"
            class="mt-1 block w-full !text-black rounded border-gray-300 shadow-sm focus:ring focus:ring-indigo-200"
          />
          @error('password') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        {{-- Powtórz hasło --}}
        <div>
          <label for="password_confirmation" class="block text-sm font-medium text-white">
            Powtórz nowe hasło
          </label>
          <input
            type="password"
            name="password_confirmation"
            id="password_confirmation"
            class="mt-1 block w-full !text-black rounded border-gray-300 shadow-sm focus:ring focus:ring-indigo-200"
          />
          @error('password_confirmation')
            <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
          @enderror
        </div>

        {{-- Przyciski --}}
        <div class="flex justify-end pt-4">
          <a
            href="{{ route('admin.users.index') }}"
            class="mr-2 px-4 py-2 bg-gray-200 !text-black text-white rounded hover:bg-gray-300"
          >
            Anuluj
          </a>
          <button
            type="submit"
            class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
          >
            Zaktualizuj
          </button>
        </div>
      </div>
    </form>
  </x-panel>

</div>
@endsection
