@extends('layouts.app')

@section('content')
<div class="max-w-md mx-auto p-6 space-y-6">

  <h1 class="text-2xl font-bold">➕ Dodaj użytkownika</h1>

  <x-panel class="p-6">
    <form method="POST" action="{{ route('admin.users.store') }}">
      @csrf

      <div class="space-y-4 ">
        {{-- Login --}}
        <div>
          <label for="login" class="block text-sm font-medium">Login</label>
          <input type="text"
                 name="login"
                 id="login"
                 value="{{ old('login') }}"
                 class="mt-1 block w-full border-gray-300 rounded shadow-sm !text-black">
          @error('login')
            <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
          @enderror
        </div>

        {{-- E-mail --}}
        <div>
          <label for="email" class="block text-sm font-medium">E-mail</label>
          <input type="email"
                 name="email"
                 id="email"
                 value="{{ old('email') }}"
                 class="mt-1 block w-full border-gray-300 rounded shadow-sm !text-black">
          @error('email')
            <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
          @enderror
        </div>

        {{-- Imię --}}
        <div>
          <label for="first_name" class="block text-sm font-medium">Imię</label>
          <input type="text"
                 name="first_name"
                 id="first_name"
                 value="{{ old('first_name') }}"
                 class="mt-1 block w-full border-gray-300 rounded shadow-sm !text-black">
          @error('first_name')
            <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
          @enderror
        </div>

        {{-- Nazwisko --}}
        <div>
          <label for="last_name" class="block text-sm font-medium">Nazwisko</label>
          <input type="text"
                 name="last_name"
                 id="last_name"
                 value="{{ old('last_name') }}"
                 class="mt-1 block w-full border-gray-300 rounded shadow-sm !text-black">
          @error('last_name')
            <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
          @enderror
        </div>

        {{-- Rola --}}
        <div>
          <label for="role" class="block text-sm font-medium">Rola</label>
          <select name="role"
                  id="role"
                  class="mt-1 block w-full border-gray-300 rounded shadow-sm !text-black">
            <option value="Administrator" @selected(old('role')=='Administrator')>Administrator</option>
            <option value="User"          @selected(old('role')=='User')>Użytkownik</option>
          </select>
          @error('role')
            <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
          @enderror
        </div>

        {{-- Aktywny --}}
        <div class="flex items-center space-x-2">
          <input type="checkbox"
                 name="is_active"
                 id="is_active"
                 value="1"
                 @checked(old('is_active', true))>
          <label for="is_active" class="text-sm">Aktywny</label>
        </div>

        {{-- Hasło --}}
        <div>
          <label for="password" class="block text-sm font-medium">Hasło</label>
          <input type="password"
                 name="password"
                 id="password"
                 class="mt-1 block w-full border-gray-300 rounded shadow-sm !text-black">
          @error('password')
            <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
          @enderror
        </div>

        {{-- Powtórz hasło --}}
        <div>
          <label for="password_confirmation" class="block text-sm font-medium">Powtórz hasło</label>
          <input type="password"
                 name="password_confirmation"
                 id="password_confirmation"
                 class="mt-1 block w-full border-gray-300 rounded shadow-sm !text-black">
        </div>

        {{-- Akcje --}}
        <div class="flex justify-end space-x-2">
          <a href="{{ route('admin.users.index') }}"
             class="px-4 py-2 bg-gray-200 rounded !text-black hover:bg-gray-300">
            Anuluj
          </a>
          <button type="submit"
                  class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">
            Dodaj
          </button>
        </div>
      </div>
    </form>
  </x-panel>

</div>
@endsection
