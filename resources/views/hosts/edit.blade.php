@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto p-6 space-y-8">

  <div class="flex items-center justify-between">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">✏️ Edytuj hosta</h1>
    <a href="{{ route('hosts.index') }}" 
       class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 text-sm">
      ← Powrót do listy
    </a>
  </div>

  @if(session('success'))
    <div class="p-4 bg-green-200 text-green-800 rounded-md">
      {{ session('success') }}
    </div>
  @endif

  @if(session('error'))
    <div class="p-4 bg-red-200 text-red-800 rounded-md">
      {{ session('error') }}
    </div>
  @endif

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    
    {{-- Główny formularz edycji --}}
    <div class="lg:col-span-2">
      <x-panel class="p-6">
        <h2 class="text-lg font-semibold mb-4">Informacje o hoście</h2>
        
        <form method="POST" action="{{ route('hosts.update', $host) }}">
          @csrf
          @method('PUT')

          <div class="space-y-4">
            
            {{-- Nazwa hosta --}}
            <div>
              <label for="host_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                Nazwa hosta *
              </label>
              <input type="text" 
                     name="host_name" 
                     id="host_name"
                     value="{{ old('host_name', $host->host_name) }}"
                     class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
                     required>
              @error('host_name')
                <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
              @enderror
            </div>

            {{-- Adres IP --}}
            <div>
              <label for="ip_address" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                Adres IP *
              </label>
              <input type="text" 
                     name="ip_address" 
                     id="ip_address"
                     value="{{ old('ip_address', $host->ip_address) }}"
                     class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
                     required
                     pattern="^((25[0-5]|(2[0-4]|1\d|[1-9]|)\d)\.?\b){4}$"
                     title="Wprowadź prawidłowy adres IPv4">
              @error('ip_address')
                <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
              @enderror
            </div>

            {{-- Opis --}}
            <div>
              <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                Opis
              </label>
              <textarea name="description" 
                        id="description"
                        rows="3"
                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
                        placeholder="Opcjonalny opis hosta...">{{ old('description', $host->description) }}</textarea>
              @error('description')
                <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
              @enderror
            </div>

            {{-- System operacyjny --}}
            <div>
              <label for="operating_system" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                System operacyjny
              </label>
              <select name="operating_system" 
                      id="operating_system"
                      class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                <option value="">-- Wybierz system --</option>
                <option value="Ubuntu 22.04" {{ old('operating_system', $host->operating_system) == 'Ubuntu 22.04' ? 'selected' : '' }}>Ubuntu 22.04</option>
                <option value="Ubuntu 20.04" {{ old('operating_system', $host->operating_system) == 'Ubuntu 20.04' ? 'selected' : '' }}>Ubuntu 20.04</option>
                <option value="Debian 12" {{ old('operating_system', $host->operating_system) == 'Debian 12' ? 'selected' : '' }}>Debian 12</option>
                <option value="Debian 11" {{ old('operating_system', $host->operating_system) == 'Debian 11' ? 'selected' : '' }}>Debian 11</option>
                <option value="CentOS 9" {{ old('operating_system', $host->operating_system) == 'CentOS 9' ? 'selected' : '' }}>CentOS 9</option>
                <option value="Rocky Linux 9" {{ old('operating_system', $host->operating_system) == 'Rocky Linux 9' ? 'selected' : '' }}>Rocky Linux 9</option>
                <option value="Windows Server 2022" {{ old('operating_system', $host->operating_system) == 'Windows Server 2022' ? 'selected' : '' }}>Windows Server 2022</option>
                <option value="Windows 11" {{ old('operating_system', $host->operating_system) == 'Windows 11' ? 'selected' : '' }}>Windows 11</option>
                <option value="Windows 10" {{ old('operating_system', $host->operating_system) == 'Windows 10' ? 'selected' : '' }}>Windows 10</option>
                <option value="macOS" {{ old('operating_system', $host->operating_system) == 'macOS' ? 'selected' : '' }}>macOS</option>
                <option value="Inne" {{ old('operating_system', $host->operating_system) == 'Inne' ? 'selected' : '' }}>Inne</option>
              </select>
              @error('operating_system')
                <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
              @enderror
            </div>

            {{-- Wersja agenta --}}
            <div>
              <label for="agent_version" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                Wersja agenta
              </label>
              <input type="text" 
                     name="agent_version" 
                     id="agent_version"
                     value="{{ old('agent_version', $host->agent_version) }}"
                     class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
                     placeholder="np. 1.0.0">
              @error('agent_version')
                <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
              @enderror
            </div>

            {{-- Status aktywności --}}
            <div class="flex items-center">
              <input type="hidden" name="is_active" value="0">
              <input type="checkbox" 
                     name="is_active" 
                     id="is_active"
                     value="1"
                     {{ old('is_active', $host->is_active) ? 'checked' : '' }}
                     class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
              <label for="is_active" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">
                Host aktywny (włącz monitoring)
              </label>
            </div>

          </div>

          {{-- Przyciski akcji --}}
          <div class="flex justify-end space-x-3 mt-6 pt-4 border-t border-gray-200 dark:border-gray-600">
            <a href="{{ route('hosts.index') }}" 
               class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600 text-sm">
              Anuluj
            </a>
            <button type="submit" 
                    class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 text-sm">
              💾 Zapisz zmiany
            </button>
          </div>

        </form>
      </x-panel>
    </div>

    {{-- Panel informacyjny --}}
    <div class="space-y-6">
      
      {{-- Status połączenia --}}
      <x-panel class="p-4">
        <h3 class="text-md font-semibold mb-3 text-gray-900 dark:text-gray-100">🔗 Status połączenia</h3>
        
        <div class="space-y-2 text-sm">
          <div class="flex justify-between">
            <span class="text-gray-600 dark:text-gray-400">Status:</span>
            <span class="font-medium
              @if($connectionStatus === 'Online') text-green-600
              @elseif($connectionStatus === 'Offline') text-red-600  
              @else text-yellow-600 @endif">
              {{ $connectionStatus }}
            </span>
          </div>
          
          @if($lastCheck)
          <div class="flex justify-between">
            <span class="text-gray-600 dark:text-gray-400">Ostatnie sprawdzenie:</span>
            <span class="font-medium text-gray-800 dark:text-gray-200">
              {{ $lastCheck->format('Y-m-d H:i:s') }}
            </span>
          </div>
          @endif
          
          <div class="flex justify-between">
            <span class="text-gray-600 dark:text-gray-400">Ostatni kontakt:</span>
            <span class="font-medium text-gray-800 dark:text-gray-200">
              {{ $host->last_contact_date ? $host->last_contact_date->format('Y-m-d H:i:s') : 'Nigdy' }}
            </span>
          </div>
        </div>

        {{-- Test połączenia --}}
        <div class="mt-4 pt-3 border-t border-gray-200 dark:border-gray-600">
          <button type="button" 
                  onclick="testConnection('{{ $host->ip_address }}')"
                  class="w-full px-3 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 text-sm">
            🔍 Testuj połączenie
          </button>
          <div id="connectionTestResult" class="mt-2 text-sm"></div>
        </div>
      </x-panel>

      {{-- Informacje systemowe --}}
      <x-panel class="p-4">
        <h3 class="text-md font-semibold mb-3 text-gray-900 dark:text-gray-100">📊 Informacje</h3>
        
        <div class="space-y-2 text-sm">
          <div class="flex justify-between">
            <span class="text-gray-600 dark:text-gray-400">ID hosta:</span>
            <span class="font-medium text-gray-800 dark:text-gray-200">{{ $host->host_id }}</span>
          </div>
          
          <div class="flex justify-between">
            <span class="text-gray-600 dark:text-gray-400">Data dodania:</span>
            <span class="font-medium text-gray-800 dark:text-gray-200">
              {{ $host->created_at->format('Y-m-d H:i') }}
            </span>
          </div>
          
          <div class="flex justify-between">
            <span class="text-gray-600 dark:text-gray-400">Ostatnia zmiana:</span>
            <span class="font-medium text-gray-800 dark:text-gray-200">
              {{ $host->updated_at->format('Y-m-d H:i') }}
            </span>
          </div>
        </div>
      </x-panel>

      {{-- Konfiguracja monitoringu --}}
      @if($host->configuration)
      <x-panel class="p-4">
        <h3 class="text-md font-semibold mb-3 text-gray-900 dark:text-gray-100">⚙️ Konfiguracja</h3>
        
        <div class="space-y-2 text-sm">
          <div class="flex justify-between">
            <span class="text-gray-600 dark:text-gray-400">Interwał zbierania:</span>
            <span class="font-medium text-gray-800 dark:text-gray-200">{{ $host->configuration->data_collection_interval }}s</span>
          </div>
          
          <div class="grid grid-cols-2 gap-2 mt-3">
            <div class="flex items-center text-xs">
              <span class="w-2 h-2 rounded-full mr-2 {{ $host->configuration->enable_cpu_monitoring ? 'bg-green-500' : 'bg-gray-400' }}"></span>
              CPU
            </div>
            <div class="flex items-center text-xs">
              <span class="w-2 h-2 rounded-full mr-2 {{ $host->configuration->enable_ram_monitoring ? 'bg-green-500' : 'bg-gray-400' }}"></span>
              RAM
            </div>
            <div class="flex items-center text-xs">
              <span class="w-2 h-2 rounded-full mr-2 {{ $host->configuration->enable_disk_monitoring ? 'bg-green-500' : 'bg-gray-400' }}"></span>
              Dysk
            </div>
            <div class="flex items-center text-xs">
              <span class="w-2 h-2 rounded-full mr-2 {{ $host->configuration->enable_network_monitoring ? 'bg-green-500' : 'bg-gray-400' }}"></span>
              Sieć
            </div>
          </div>
        </div>

        <div class="mt-4 pt-3 border-t border-gray-200 dark:border-gray-600">
          <a href="{{ route('hosts.config', $host) }}" 
             class="w-full block text-center px-3 py-2 bg-gray-600 text-white rounded hover:bg-gray-700 text-sm">
            ⚙️ Edytuj konfigurację
          </a>
        </div>
      </x-panel>
      @endif

      {{-- Szybkie akcje --}}
      <x-panel class="p-4">
        <h3 class="text-md font-semibold mb-3 text-gray-900 dark:text-gray-100">⚡ Szybkie akcje</h3>
        
        <div class="space-y-2">
          <a href="{{ route('hosts.show', $host) }}" 
             class="w-full block text-center px-3 py-2 bg-green-600 text-white rounded hover:bg-green-700 text-sm">
            👁️ Podgląd szczegółów
          </a>
          
          <a href="{{ route('hosts.metrics', $host) }}" 
             class="w-full block text-center px-3 py-2 bg-purple-600 text-white rounded hover:bg-purple-700 text-sm">
            📈 Metryki i wykresy
          </a>
          
          <form method="POST" action="{{ route('hosts.destroy', $host) }}" class="w-full">
            @csrf
            @method('DELETE')
            <button type="submit" 
                    onclick="return confirm('Czy na pewno chcesz usunąć hosta {{ $host->host_name }}? Ta operacja jest nieodwracalna!')"
                    class="w-full px-3 py-2 bg-red-600 text-white rounded hover:bg-red-700 text-sm">
              🗑️ Usuń hosta
            </button>
          </form>
        </div>
      </x-panel>

    </div>
  </div>
</div>

{{-- JavaScript dla testowania połączenia --}}
<script>
function testConnection(ipAddress) {
    const resultDiv = document.getElementById('connectionTestResult');
    const button = event.target;
    
    // Pokaż stan ładowania
    button.disabled = true;
    button.innerHTML = '⏳ Testowanie...';
    resultDiv.innerHTML = '<div class="text-blue-600">Sprawdzanie połączenia...</div>';
    
    // Simulated connection test - replace with actual AJAX call
    setTimeout(() => {
        // Reset button
        button.disabled = false;
        button.innerHTML = '🔍 Testuj połączenie';
        
        // Simulate test result
        const isReachable = Math.random() > 0.3; // 70% success rate for demo
        
        if (isReachable) {
            resultDiv.innerHTML = '<div class="text-green-600">✅ Host dostępny</div>';
        } else {
            resultDiv.innerHTML = '<div class="text-red-600">❌ Host niedostępny</div>';
        }
        
        // Clear result after 5 seconds
        setTimeout(() => {
            resultDiv.innerHTML = '';
        }, 5000);
        
    }, 2000);
}
</script>
@endsection