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
#### PowerShell:
```powershell
# Skopiuj plik konfiguracyjny (jeśli nie masz .env)
Copy-Item .env.example .env

# Wygeneruj klucz aplikacji
php artisan key:generate
```

#### Bash/Zsh/Ksh:
```bash
# Skopiuj plik konfiguracyjny (jeśli nie masz .env)
cp .env.example .env

# Wygeneruj klucz aplikacji
php artisan key:generate
```

**WAŻNE**: Sprawdź plik `.env` - upewnij się, że konfiguracja bazy jest poprawna:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=zenmon_db
DB_USERNAME=root
DB_PASSWORD=root
```

### 5. Weryfikacja konfiguracji Docker

**Sprawdź plik `docker-compose.yml`** - upewnij się, że sekcja volumes wygląda tak:
```yaml
volumes:
  zenmon_mysql_data:
    name: zenmon_mysql_data
```

### 6. Uruchomienie bazy danych (Docker)
```bash
# Identyczne dla wszystkich terminali
docker-compose up -d

# Sprawdź status kontenera
docker-compose ps

# Sprawdź logi MySQL (czy się uruchomił)
docker logs zenmon_mysql
```

### 7. Migracja bazy danych
```bash
# ⚠️ WAŻNE: Poczekaj 30-60 sekund na uruchomienie MySQL!

# Uruchom migracje i załaduj dane testowe - identyczne dla wszystkich terminali
php artisan migrate:fresh --seed
```

**Jeśli błąd połączenia z bazą:**
```bash
# Sprawdź czy MySQL jest gotowy - identyczne dla wszystkich terminali
docker exec zenmon_mysql mysql -u root -p -e "SHOW DATABASES;"

# Jeśli nie działa, restartuj kontener - identyczne dla wszystkich terminali
docker-compose restart mysql
```

### 8. Uruchomienie aplikacji webowej
```bash
# Uruchom serwer Laravel na wszystkich interfejsach - identyczne dla wszystkich terminali
php artisan serve --host=0.0.0.0 --port=8001
```

#### Drugi terminal - budowanie assets:

**PowerShell:**
```powershell
# W NOWYM oknie PowerShell - buduj assets
npm run dev
```

**Bash/Zsh/Ksh:**
```bash
# W NOWYM terminalu - buduj assets
npm run dev
```

### 9. Sprawdzenie instalacji
Otwórz przeglądarkę i przejdź do: `http://localhost:8001`

**Domyślne konto administratora:**
- Login: `admin`
- Hasło: `admin123`

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
# Identyczne dla wszystkich terminali
php artisan test

# Testy z szczegółowym outputem
php artisan test --verbose

# Konkretny test
php artisan test --filter=AgentApiTest

# Testy z coverage (jeśli skonfigurowane)
php artisan test --coverage
```

### 📋 Wymagania dla testów

**Przed pierwszym uruchomieniem testów:**
```powershell
# Utwórz plik konfiguracyjny dla testów
Copy-Item .env .env.testing

# Edytuj .env.testing i zmień tylko te linie:
# DB_CONNECTION=sqlite
# DB_DATABASE=:memory:
# TELESCOPE_ENABLED=false

# Przygotuj cache dla testów
php artisan config:cache --env=testing
```

### ⚠️ WAŻNE: Bezpieczne uruchamianie testów

**Testy używają SQLite w pamięci, ale konfiguracja może wpływać na główną bazę MySQL.**

#### Bezpieczne uruchamianie testów:
```powershell
# Uruchom testy (automatycznie używa SQLite)
php artisan config:cache --env=testing
php artisan test

# NATYCHMIAST wyczyść cache po testach
php artisan config:clear
```

#### Po każdym uruchomieniu testów sprawdź bazę:
```powershell
# Sprawdź czy aplikacja używa MySQL
php artisan config:show database.default  
# Powinno pokazać: "mysql"
```

#### Jeśli dane MySQL zostały uszkodzone:
```powershell
# Przywróć MySQL z pełnymi danymi
php artisan config:clear
php artisan migrate:fresh --seed
```

#### Struktura baz danych:
- **MySQL** (`zenmon_db`) - główna baza aplikacji z pełnymi danymi
- **SQLite** (`:memory:`) - tymczasowa baza tylko dla testów

**🚨 Nigdy nie uruchamiaj `config:cache` bez `--env=testing`!**

### 🧪 Typy testów w projekcie

- **Unit Tests** (`tests/Unit/`) - Testowanie modeli i logiki biznesowej
- **Feature Tests** (`tests/Feature/`) - Testowanie API endpoints i workflow
- **Specific Test Suites:**
  - `AgentApiTest` - API dla agentów Python
  - `HostApiTest` - Zarządzanie hostami
  - `AlertSystemTest` - System alertów i powiadomień
  - `SecurityTest` - Testy bezpieczeństwa API

### 🔧 Rozwiązywanie problemów z testami

#### Problem: "Configuration not found" lub błędy SQLite
```powershell
# Sprawdź czy .env.testing istnieje
Get-Content .env.testing

# Jeśli nie ma pliku, utwórz go:
Copy-Item .env .env.testing
# Edytuj .env.testing zgodnie z instrukcjami powyżej
```

#### Problem: Testy nadal używają MySQL
```powershell
# Sprawdź aktualną konfigurację testów
php artisan config:show database.default --env=testing

# Jeśli pokazuje "mysql" zamiast "sqlite":
php artisan config:clear
php artisan config:cache --env=testing
```

#### Problem: Błędy z Telescope w testach
```powershell
# Upewnij się że w .env.testing jest:
# TELESCOPE_ENABLED=false
```

### 🏃‍♂️ Workflow deweloperski z testami

```powershell
# 1. Przed rozpoczęciem pracy - sprawdź testy
php artisan config:cache --env=testing
php artisan test
php artisan config:clear

# 2. Podczas developmentu - uruchamiaj konkretne testy
php artisan config:cache --env=testing
php artisan test --filter=NazwaTestu
php artisan config:clear

# 3. Przed commitem - uruchom wszystkie testy
php artisan config:cache --env=testing
php artisan test
php artisan config:clear

# 4. Jeśli testy zepsuły MySQL - przywróć dane
php artisan migrate:fresh --seed
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
- **MySQL**: Docker logs (`docker logs zenmon_mysql`)

### Debugging

#### PowerShell:
```powershell
# Tail logów Laravel (PowerShell)
Get-Content storage/logs/laravel.log -Wait

# Logi MySQL container
docker logs -f zenmon_mysql

# Czyszczenie cache
php artisan cache:clear
php artisan config:clear
php artisan route:clear
```

#### Bash/Zsh/Ksh:
```bash
# Tail logów Laravel
tail -f storage/logs/laravel.log

# Logi MySQL container
docker logs -f zenmon_mysql

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

## ⚠️ Rozwiązywanie Problemów

### Problem: "No tables" w bazie danych

#### PowerShell:
```powershell
# Sprawdź czy MySQL działa
docker logs zenmon_mysql

# Sprawdź połączenie
php artisan tinker
# W tinker: DB::connection()->getPdo();

# Uruchom migracje ponownie
php artisan migrate:fresh --seed
```

#### Bash/Zsh/Ksh:
```bash
# Sprawdź czy MySQL działa
docker logs zenmon_mysql

# Sprawdź połączenie
php artisan tinker
# W tinker: DB::connection()->getPdo();

# Uruchom migracje ponownie
php artisan migrate:fresh --seed
```

### Problem: Volume z danymi zostaje usunięty
**Przyczyna**: Niepoprawna konfiguracja nazwy volume w `docker-compose.yml`  
**Rozwiązanie**: Upewnij się, że sekcja volumes zawiera:
```yaml
volumes:
  zenmon_mysql_data:
    name: zenmon_mysql_data
```

### Problem: "Connection refused" do MySQL

#### PowerShell:
```powershell
# Sprawdź czy kontener działa
docker-compose ps

# Sprawdź porty
netstat -an | Select-String "3306"

# Restartuj MySQL
docker-compose restart mysql
```

#### Bash/Zsh/Ksh:
```bash
# Sprawdź czy kontener działa
docker-compose ps

# Sprawdź porty (Linux/macOS)
netstat -an | grep 3306
# lub na nowszych systemach:
ss -tuln | grep 3306

# Restartuj MySQL
docker-compose restart mysql
```

### Problem: Agent nie może się połączyć

#### PowerShell:
```powershell
# Sprawdź czy Laravel działa na 0.0.0.0 (nie 127.0.0.1)
php artisan serve --host=0.0.0.0 --port=8001

# Sprawdź firewall (Windows)
# Zezwól na port 8001 w Windows Defender
```

#### Bash/Zsh/Ksh:
```bash
# Sprawdź czy Laravel działa na 0.0.0.0 (nie 127.0.0.1)
php artisan serve --host=0.0.0.0 --port=8001

# Sprawdź firewall (Linux)
sudo ufw status
sudo ufw allow 8001

# Sprawdź firewall (macOS)
sudo pfctl -sr | grep 8001
```

## 🚀 Deployment (Produkcja)

#### PowerShell:
```powershell
# Optymalizacja dla produkcji
composer install --no-dev --optimize-autoloader
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

#### Bash/Zsh/Ksh:
```bash
# Optymalizacja dla produkcji
composer install --no-dev --optimize-autoloader
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Zmienne środowiskowe (produkcja)
```env
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
```bash
# Identyczne dla wszystkich terminali
php artisan make:migration
php artisan make:model
php artisan make:controller
php artisan make:test
# Dokumentuj w Swagger (adnotacje OA)
```

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