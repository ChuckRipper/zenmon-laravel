@extends('layouts.app')
@section('title', '403 – Dostęp zabroniony')

@section('content')
<div class="min-h-screen flex items-center justify-center">
  <div class="bg-white dark:bg-gray-800 p-8 rounded shadow-md max-w-md text-center">
    <h1 class="text-4xl font-bold text-red-600 mb-4">403</h1>
    <p class="text-lg text-gray-700 dark:text-gray-300 mb-2">Dostęp zabroniony</p>
    <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">
      {{ $error ?? 'Administrator privileges required' }}
    </p>
    <a href="{{ url()->previous() }}" 
       class="inline-block bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
      ← Wróć
    </a>
  </div>
</div>
@endsection