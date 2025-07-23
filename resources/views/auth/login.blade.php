{{-- resources/views/auth/login.blade.php --}}
@extends('layouts.app')

@section('content')
<div class="min-h-screen flex items-center justify-center bg-gray-100 dark:bg-gray-900">
  <div class="w-full max-w-md bg-white p-8 rounded-xl shadow-lg dark:bg-gray-800">

    {{-- Logo nad formularzem --}}
    <div class="flex flex-col items-center mb-1">
      <img
        src="{{ asset('images/logo.png') }}"
        alt="ZenMon Logo"
        class="w-100 h-100"
      />
    </div>

    <form method="POST" action="{{ route('login.post') }}">
      @csrf

      <!-- Login -->
      <div class="mb-4">
        <label for="login" class="block text-sm font-medium mb-1 text-gray-700 dark:text-gray-300">
          Login
        </label>
        <input
          id="login"
          name="login"
          type="text"
          value="{{ old('login') }}"
          required
          class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-gray-100 border-gray-300 dark:border-gray-600"
        >
        @error('login')
          <p class="text-red-600 text-sm mt-1 dark:text-red-400">{{ $message }}</p>
        @enderror
      </div>

      <!-- Hasło -->
      <div class="mb-4">
        <label for="password" class="block text-sm font-medium mb-1 text-gray-700 dark:text-gray-300">
          Hasło
        </label>
        <input
          id="password"
          name="password"
          type="password"
          required
          class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-gray-100 border-gray-300 dark:border-gray-600"
        >
        @error('password')
          <p class="text-red-600 text-sm mt-1 dark:text-red-400">{{ $message }}</p>
        @enderror
      </div>

      <!-- Submit -->
      <div class="flex justify-end">
        <button
          type="submit"
          class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-blue-500 dark:hover:bg-blue-600"
        >
          Zaloguj
        </button>
      </div>
    </form>
  </div>
</div>
@endsection
