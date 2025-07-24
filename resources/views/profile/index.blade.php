@extends('layouts.app')

@section('content')
<div class="max-w-md mx-auto p-6 space-y-6">

  <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">🔒 Zmiana hasła</h1>

  {{-- Sukces --}}
  @if(session('success'))
    <div class="p-4 bg-green-100 text-green-800 rounded">
      {{ session('success') }}
    </div>
  @endif

  {{-- Formularz --}}
  <x-panel class="p-6">
    <form method="POST" action="{{ route('profile.updatePassword') }}">
      @csrf

      <div class="space-y-4">
        {{-- Obecne hasło --}}
        <div>
          <label for="current_password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
            Obecne hasło
          </label>
          <input
            type="password"
            name="current_password"
            id="current_password"
            class="mt-1 block w-full border-gray-300 rounded shadow-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white"
          >
          @error('current_password')
            <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
          @enderror
        </div>

        {{-- Nowe hasło --}}
        <div>
          <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
            Nowe hasło
          </label>
          <input
            type="password"
            name="password"
            id="password"
            class="mt-1 block w-full border-gray-300 rounded shadow-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white"
          >
          @error('password')
            <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
          @enderror
        </div>

        {{-- Powtórz nowe hasło --}}
        <div>
          <label for="password_confirmation" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
            Powtórz nowe hasło
          </label>
          <input
            type="password"
            name="password_confirmation"
            id="password_confirmation"
            class="mt-1 block w-full border-gray-300 rounded shadow-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white"
          >
        </div>

        {{-- Przycisk --}}
        <div class="flex justify-end">
          <button
            type="submit"
            class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700"
          >
            Zmień hasło
          </button>
        </div>
      </div>
    </form>
  </x-panel>

</div>
@endsection
