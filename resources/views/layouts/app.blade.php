{{-- resources/views/layouts/app.blade.php --}}
<!DOCTYPE html>
<html lang="pl" class="dark">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>{{ config('app.name','ZenMon') }}</title>

  {{-- Favicon --}}
  <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}" sizes="32x32" />
  <link rel="apple-touch-icon" href="{{ asset('images/logo.png') }}" />

  {{-- Tailwind config (musi być PRZED CDN) --}}
  <script>
    window.tailwindConfig = {
      darkMode: 'class',
      // tu możesz dorzucić inne ustawienia, np. rozszerzenia
    }
  </script>

  {{-- Tailwind CSS via CDN --}}
  <script src="https://cdn.tailwindcss.com"></script>

  {{-- ewentualne dodatkowe meta/style z widoków --}}
  @stack('head')
</head>
<body class="bg-gray-100 text-gray-900 dark:bg-gray-900 dark:text-gray-100">

  @auth
    <div class="flex h-screen">
      {{-- Boczne menu --}}
      <x-nav class="w-64 flex-shrink-0" />

      <div class="flex-1 flex flex-col">
        {{-- Header --}}
        <header class="bg-white dark:bg-gray-800 shadow px-6 py-4 flex justify-between items-center">
          <div class="flex items-center justify-center w-full">
            <img src="{{ asset('images/logo.png') }}" alt="ZenMon Logo" class="w-40 h-40" />
          </div>
          <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="text-red-600 hover:underline dark:text-red-400">
              Wyloguj
            </button>
          </form>
        </header>

        {{-- Główna zawartość --}}
        <main class="flex-1 overflow-auto px-6 py-4">
          @yield('content')
        </main>

        {{-- Stopka --}}
        <footer class="bg-white dark:bg-gray-800 text-center text-sm py-4">
          &copy; {{ date('Y') }} ZenMon
        </footer>
      </div>
    </div>
  @else
    {{-- Layout dla gości (login itp.) --}}
    <div class="min-h-screen flex items-center justify-center px-4">
      @yield('content')
    </div>
  @endauth

{{-- Skrypty dodawane przez widoki --}}
@stack('scripts')
{{-- Chart.js (UMD build) – globalne Chart będzie zawsze dostępne --}}
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.3.0/dist/chart.umd.min.js"></script>

{{-- Skrypty dodawane przez widoki --}}
@stack('scripts')
</body>
</html>
