@extends('layouts.app')

@section('content')
<div class="max-w-md mx-auto p-6 space-y-4">
  <h1 class="text-2xl font-bold">📥 Import konfiguracji</h1>

  @if(session('error'))
    <div class="p-3 bg-red-200 text-red-800 rounded">
      {{ session('error') }}
    </div>
  @endif

  @if(session('success'))
    <div class="p-3 bg-green-200 text-green-800 rounded">
      {{ session('success') }}
    </div>
  @endif

  <form method="POST" action="{{ route('admin.tools.import') }}" enctype="multipart/form-data">
    @csrf

    <div class="mb-4">
      <label for="config_file" class="block text-sm font-medium mb-1">
        Wybierz plik JSON
      </label>
      <input type="file"
             name="config_file"
             id="config_file"
             class="block w-full text-sm text-gray-900 bg-gray-50 rounded border border-gray-300 cursor-pointer focus:outline-none">
      @error('config_file')
        <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
      @enderror
    </div>

    <button type="submit"
            class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700">
      Importuj
    </button>
  </form>
</div>
@endsection
