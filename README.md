# ZenMon - Aplikacja Webowa Laravel

**ZenMon** to minimalistyczna aplikacja do monitoringu infrastruktury IT, będąca alternatywą dla systemów takich jak Zabbix czy Nagios. Nazwa "Zen" odzwierciedla filozofię prostoty i przystępności dla każdego użytkownika.

## 📋 Opis Projektu

ZenMon składa się z dwóch głównych komponentów:
- **Aplikacja webowa** (Laravel) - centralne API i interfejs użytkownika
- **Agent** (Python) - aplikacja zbierająca dane na monitorowanych hostach

### Wersja: MVP (Minimum Viable Product)

## 🏗️ Architektura

- **Framework**: Laravel 11.x
- **Baza danych**: MySQL 8.0 (skonteneryzowana w Docker)
- **Uwierzytelnianie**: Laravel Sanctum (Bearer tokens)
- **API**: RESTful z dokumentacją Swagger/OpenAPI
- **Monitoring**: Laravel Telescope
- **Frontend**: Blade templates + Bootstrap + JavaScript

## 🛠️ Użyte Technologie i Narzędzia

### Backend
- PHP 8.2+
- Laravel 11.x
- MySQL 8.0
- Docker & Docker Compose
- Laravel Sanctum (API authentication)
- Laravel Telescope (debugging)
- Swagger/OpenAPI (dokumentacja API)

### Frontend
- Bootstrap 5.3
- Tailwind CSS (dodatkowe style)
- Chart.js / ApexCharts (wizualizacje)
- Feather Icons / Lucide
- AOS (animacje)
- SweetAlert2 (powiadomienia)

### Narzędzia deweloperskie
- PHPUnit (testy)
- Composer (zarządzanie zależnościami PHP)
- npm/Vite (budowanie assets)

## 📁 Struktura Projektu

```
zenmon_laravel/
├── app/
│   ├── Http/
│   │   ├── Controllers/        # Kontrolery API
│   │   └── Resources/          # Transformery odpowiedzi API
│   ├── Models/                 # Modele Eloquent
│   └── Services/               # Logika biznesowa
├── config/                     # Konfiguracja Laravel
├── database/
│   ├── migrations/             # Migracje bazy danych
│   └── factories/              # Fabryki testowe
├── routes/
│   ├── api.php                # Trasy API
│   └── web.php                # Trasy webowe
├── tests/
│   ├── Feature/               # Testy funkcjonalne
│   └── Unit/                  # Testy jednostkowe
├── docker-compose.yml         # Konfiguracja MySQL
└── README.md
```

## 🚀 Instrukcja Instalacji i Uruchomienia

### Wymagania
- PHP 8.2+
- Composer 2.x
- Node.js 18+ & npm
- Docker Desktop
- PowerShell (Windows) lub Terminal (Linux/macOS)

### 1. Klonowanie repozytorium
```bash
git clone https://github.com/ChuckRipper/zenmon-laravel.git
cd zenmon-laravel
```

### 2. Instalacja zależności PHP
```bash
composer install
```

### 3. Instalacja zależności JavaScript
```bash
npm install
```

### 4. Konfiguracja środowiska
```bash
# Skopiuj plik konfiguracyjny
cp .env.example .env

# Wygeneruj klucz aplikacji
php artisan key:generate
```

### 5. Uruchomienie bazy danych (Docker)
```bash
# Uruchom MySQL w kontenerze
docker-compose up -d

# Sprawdź status kontenera
docker-compose ps
```

### 6. Migracja bazy danych
```bash
# Uruchom migracje
php artisan migrate

# Opcjonalnie: Załaduj dane testowe
php artisan db:seed
```

### 7. Uruchomienie aplikacji webowej
```bash
# Uruchom serwer Laravel (domyślnie na porcie 8001)
php artisan serve --port=8001

# W osobnym terminalu - buduj assets
npm run dev
```

### 8. Sprawdzenie instalacji
Otwórz przeglądarkę i przejdź do: `http://localhost:8001`

## 🔧 Konfiguracja API i Swagger

### 🔑 Pobranie tokenu API (Oneliners)

#### PowerShell
```powershell
$token = (Invoke-RestMethod -Uri "http://localhost:8001/api/login" -Method POST -Body (@{login="admin"; password="admin123"} | ConvertTo-Json) -ContentType "application/json").token; Write-Host "Bearer Token: $token"
```

#### Linux/macOS Terminal
```bash
token=$(curl -s -X POST http://localhost:8001/api/login -H "Content-Type: application/json" -d '{"login":"admin","password":"admin123"}' | jq -r .token) && echo "Bearer Token: $token"
```

### Dostęp do Swagger
1. Przejdź do: `http://localhost:8001/api/documentation`
2. Kliknij "Authorize"
3. Wpisz: `Bearer [TWÓJ_TOKEN]`
4. Testuj endpointy API

### Laravel Telescope (Debugging)
- URL: `http://localhost:8001/telescope`
- Monitoruj requesty, queries, jobs, mail itp.

## 🧪 Uruchamianie Testów

```bash
# Wszystkie testy
php artisan test

# Testy z szczegółowym outputem
php artisan test --verbose

# Konkretny test
php artisan test --filter=AgentApiTest

# Testy z coverage (jeśli skonfigurowane)
php artisan test --coverage
```

## 📊 Główne Funkcjonalności

### UC30: Zbieranie metryk systemowych
- CPU, RAM, dysk, sieć
- Monitorowanie katalogów
- Automatyczne wykrywanie systemu operacyjnego

### UC31: API dla agentów
- Uwierzytelnianie Bearer token
- Batch submission metryk
- Heartbeat monitoring
- Walidacja danych

### UC41-43: System alertów
- Automatyczne generowanie alertów
- Progi ostrzeżeń (Warning/Critical)
- Powiadomienia (email, Slack)
- API zarządzania alertami

## 🔗 Integracja z Agentem

Aplikacja współpracuje z agentem Python (`zenmon_agent_python`):

1. Agent uwierzytelnia się: `POST /api/login`
2. Otrzymuje Bearer token
3. Wysyła metryki: `POST /api/agent/metrics/batch`
4. Wysyła heartbeat: `POST /api/agent/heartbeat/{host_id}`

## 🐛 Debugging i Logi

### Lokalizacja logów
- **Laravel**: `storage/logs/laravel.log`
- **MySQL**: Docker logs (`docker-compose logs mysql`)

### Debugging
```bash
# Tail logów Laravel
tail -f storage/logs/laravel.log

# Logi MySQL container
docker-compose logs -f mysql

# Czyszczenie cache
php artisan cache:clear
php artisan config:clear
php artisan route:clear
```

## 🔒 Bezpieczeństwo

- **Hasła**: Hash SHA-256 (Laravel)
- **API**: Bearer token authentication
- **Walidacja**: Laravel Form Requests
- **CORS**: Skonfigurowane dla localhost
- **Rate limiting**: API endpoints

## 🚀 Deployment (Produkcja)

```bash
# Optymalizacja dla produkcji
composer install --no-dev --optimize-autoloader
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Zmienne środowiskowe (produkcja)
```bash
APP_ENV=production
APP_DEBUG=false
# URL i host pozostają jak w developerskim .env dla tej fazy projektu
# Kompletny production config będzie w przyszłych iteracjach
```

## 📞 Wsparcie

W przypadku problemów:
1. Sprawdź logi: `storage/logs/laravel.log`
2. Sprawdź status Docker: `docker-compose ps`
3. Sprawdź konfigurację: `.env`
4. Uruchom testy: `php artisan test`

## 🤝 Rozwój

### Dodanie nowej funkcjonalności
1. Utwórz migrację: `php artisan make:migration`
2. Utwórz model: `php artisan make:model`
3. Utwórz kontroler: `php artisan make:controller`
4. Dodaj testy: `php artisan make:test`
5. Dokumentuj w Swagger (adnotacje OA)

### Konwencje kodu
- Używaj regionów PHP (jak w C#)
- Dokumentuj wszystkie metody (`/// <summary>`)
- Programowanie obiektowe z zachowaniem SOLID
- Testy dla każdej nowej funkcjonalności

---

**Autorzy**: Cezary Kalinowski i Przemysław Jancewicz
**Wersja**: MVP 1.0  
**Laravel**: 11.x  
**PHP**: 8.2+