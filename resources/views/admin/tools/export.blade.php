@extends('layouts.app')

@section('content')
<div class="max-w-md mx-auto p-6 space-y-4">
  <h1 class="text-2xl font-bold">📤 Eksport konfiguracji</h1>

  <p>Kliknij przycisk, aby pobrać aktualną konfigurację w formacie JSON.</p>

  <form method="POST" action="{{ route('admin.tools.export') }}">
    @csrf
    <button type="submit"
            class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
      Eksportuj
    </button>
  </form>
</div>
@endsection
