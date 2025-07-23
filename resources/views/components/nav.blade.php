@props(['class'=>''])

<nav {{ $attributes->merge(['class'=> "bg-white dark:bg-gray-800 $class"]) }}>
  <ul class="p-6 space-y-2">
    @if (Route::has('dashboard'))
      <li>
        <a href="{{ route('dashboard') }}"
           class="block px-4 py-2 rounded hover:bg-gray-100 dark:hover:bg-gray-700 {{ request()->routeIs('dashboard') ? 'font-semibold' : '' }}">
          Dashboard
        </a>
      </li>
    @endif

    @if (Route::has('hosts.index'))
      <li>
        <a href="{{ route('hosts.index') }}"
           class="block px-4 py-2 rounded hover:bg-gray-100 dark:hover:bg-gray-700 {{ request()->routeIs('hosts.index') ? 'font-semibold' : '' }}">
          Hosty
        </a>
      </li>
    @endif

    @if (Route::has('alerts.index'))
      <li>
        <a href="{{ route('alerts.index') }}"
           class="block px-4 py-2 rounded hover:bg-gray-100 dark:hover:bg-gray-700 {{ request()->routeIs('alerts.*') ? 'font-semibold' : '' }}">
          Alerty
        </a>
      </li>
    @endif

    @if (Route::has('config.shortcut'))
      <li>
        <a href="{{ route('config.shortcut') }}"
           class="block px-4 py-2 rounded hover:bg-gray-100 dark:hover:bg-gray-700 {{ request()->routeIs('hosts.config') ? 'font-semibold' : '' }}">
          Konfiguracja hosta
        </a>
      </li>
    @endif

    @if (Route::has('admin.index'))
      <li>
        <a href="{{ route('admin.index') }}"
           class="block px-4 py-2 rounded hover:bg-gray-100 dark:hover:bg-gray-700 {{ request()->routeIs('profile.*') ? 'font-semibold' : '' }}">
          Panel administratora
        </a>
      </li>
    @endif
  </ul>
</nav>
