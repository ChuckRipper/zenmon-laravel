<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder; // Database seeder class for Laravel
use Illuminate\Support\Facades\Hash; // Hash facade for password hashing
use App\Models\User; // User model for authentication and management
use App\Models\Host; // Host model for monitored servers
use App\Models\MetricType; // MetricType model for defining metric types
use App\Models\HostConfiguration; // HostConfiguration model for host settings
use App\Models\AlertThreshold; // AlertThreshold model for alert thresholds
use App\Models\Metric; // Metric model for storing metric data
use App\Models\Alert; // Alert model for managing alerts
use App\Models\MonitoredDirectory; // MonitoredDirectory model for directory monitoring
use App\Models\DirectoryMetric; // DirectoryMetric model for directory metrics
use App\Services\MetricService; // MetricService for handling metric operations
use App\Services\AlertService; // AlertService for managing alerts
use Carbon\Carbon; // Carbon for date and time manipulation

/**
 * Seeder for ZenMon application sample data
 */
class ZenMonSeeder extends Seeder
{
    #region Public Methods
    public function run(): void
    {
        // Check if this is initial seeding or just adding users
        // $onlyUsers = $this->command->option('only-users') ?? false;
        // $userCount = $this->command->option('users') ?? 5;

        // if ($onlyUsers) {
        //     $this->createTestUsers($userCount);
        //     return;
        // }

        $userCount = 5; // Default count for test users
        
        // 1. System users - fixed accounts
    
        // 1.1. Admin user zgodnie z dokumentacją UML
        if (!User::where('login', 'admin')->exists()) {
            $admin = User::create([
                'login' => 'admin',
                'password' => Hash::make('admin123'),
                'email' => 'admin@zenmon.local',
                'first_name' => 'System',
                'last_name' => 'Administrator',
                'role' => 'Administrator',
                'is_active' => true
            ]);
            $this->command->info("✅ Created admin user");
        } else {
            $admin = User::where('login', 'admin')->first();
            $this->command->info("ℹ️  Admin user already exists");
        }

        // 1.2. Agent user - dedykowany dla agentów
        if (!User::where('login', 'zenmon_agent')->exists()) {
            $agent = User::create([
                'login' => 'zenmon_agent',
                'password' => Hash::make('zenmon_agent123'),
                'email' => 'agent@zenmon.local',
                'first_name' => 'ZenMon',
                'last_name' => 'Agent',
                'role' => 'Agent',
                'is_active' => true
            ]);
            $this->command->info("✅ Created agent user");
        } else {
            $agent = User::where('login', 'zenmon_agent')->first();
            $this->command->info("ℹ️  Agent user already exists");
        }

        // 1.3. Userzy testowi - dynamiczne tworzenie userów, 5 za każdym razem
        // $this->createTestUsers($userCount);

        echo "Creating 5 test users...\n";
        $testUsers = User::factory()
                        ->count(5)
                        ->regularUser()
                        ->recentlyLoggedIn()
                        ->create();
        
        echo "✅ Created 5 test users\n";

        // 2. Podstawowe typy metryk - bezpieczne sprawdzenie
        
        $cpuType = MetricType::where('metric_name', 'CPU')->first();
        if (!$cpuType) {
            $cpuType = MetricType::create([
                'metric_name' => 'CPU',
                'unit' => '%',
                'description' => 'Wykorzystanie procesora'
            ]);
            echo "✅ Created CPU metric type\n";
        } else {
            echo "ℹ️  CPU metric type already exists\n";
        }

        $ramType = MetricType::where('metric_name', 'RAM')->first();
        if (!$ramType) {
            $ramType = MetricType::create([
                'metric_name' => 'RAM',
                'unit' => '%', 
                'description' => 'Wykorzystanie pamięci RAM'
            ]);
            echo "✅ Created RAM metric type\n";
        } else {
            echo "ℹ️  RAM metric type already exists\n";
        }

        $networkType = MetricType::where('metric_name', 'Network')->first();
        if (!$networkType) {
            $networkType = MetricType::create([
                'metric_name' => 'Network',
                'unit' => 'ms',
                'description' => 'Czas odpowiedzi sieci'
            ]);
            echo "✅ Created Network metric type\n";
        } else {
            echo "ℹ️  Network metric type already exists\n";
        }

        // 2.1. Storage types (50 slotów na dyski Windows / katalogi Linux)
        $storageTypes = [];
        echo "Processing Storage metric types...\n";
        
        for ($i = 4; $i <= 53; $i++) {
            $existing = MetricType::where('metric_name', "Storage-{$i}")->first();
            if (!$existing) {
                $storageTypes[] = MetricType::create([
                    'metric_name' => "Storage-{$i}",
                    'unit' => '%',
                    'description' => "Disk/Directory usage slot {$i}"
                ]);
                if ($i % 10 == 0) echo "✅ Created Storage-{$i}\n"; // Info co 10
            } else {
                $storageTypes[] = $existing;
            }
        }
        echo "✅ All Storage metric types processed\n";

        // 2.1. Storage types (50 slotów na dyski Windows / katalogi Linux)
        // $storageTypes = [];
        // for ($i = 4; $i <= 53; $i++) {
        //     $storageTypes[] = MetricType::create([
        //         'metric_name' => "Storage-{$i}",
        //         'unit' => '%',
        //         'description' => "Disk/Directory usage slot {$i}"
        //     ]);
        // }

        $storageTypes = [];
        for ($i = 4; $i <= 53; $i++) {
            $storageTypes[] = MetricType::firstOrCreate(
                ['metric_name' => "Storage-{$i}"],
                [
                    'unit' => '%',
                    'description' => "Disk/Directory usage slot {$i}"
                ]
            );
        }

        echo "✅ All Storage metric types processed\n";

        // 3. Hosty testowe - dynamiczne wykrywanie lokalnego systemu
        echo "🏠 Starting hosts creation...\n";

        // 3.1. Lokalny host (dynamiczne wykrywanie)
        $localHostName = gethostname() ?: 'localhost';
        $localSystem = $this->detectOperatingSystem();
        $localDescription = $this->generateHostDescription($localSystem);
        
        echo "Creating local host: {$localHostName}\n";

        // $localHost = Host::create([
        //     'host_name' => $localHostName,
        //     'ip_address' => '127.0.0.1',
        //     'description' => $localDescription,
        //     'operating_system' => $localSystem,
        //     'agent_version' => '2.0',
        //     'is_active' => true
        
        $localHost = Host::where('ip_address', '127.0.0.1')->first();
            if (!$localHost) {
                $localHost = Host::create([
                    'host_name' => $localHostName,
                    'ip_address' => '127.0.0.1',
                    'description' => $localDescription,
                    'operating_system' => $localSystem,
                    'agent_version' => '2.0',
                    'is_active' => true
                ]);
                echo "✅ Created local host: {$localHostName}\n";
            } else {
                echo "ℹ️  Local host already exists: {$localHost->host_name}\n";
            }
        
        // ]);

        echo "✅ Local host created\n";
        echo "🐧 Creating Linux test hosts...\n";

        // 3.2. Hosty testowe - kontenery Docker (dynamiczne IP)
        $dockerNetwork = $this->getDockerNetworkBase();
        
        // $alpineHost = Host::create([
        //     'host_name' => 'alpine-test-host',
        //     'ip_address' => $dockerNetwork . '.3',
        //     'description' => 'Alpine Linux test container',
        //     'operating_system' => 'Alpine Linux 3.18',
        //     'agent_version' => '2.0',
        //     'is_active' => true
        // ]);

        $alpineHost = Host::where('ip_address', $dockerNetwork . '.3')->first();
        if (!$alpineHost) {
            $alpineHost = Host::create([
                'host_name' => 'alpine-test-host',
                'ip_address' => $dockerNetwork . '.3',
                'description' => 'Alpine Linux test container',
                'operating_system' => 'Alpine Linux 3.18',
                'agent_version' => '2.0',
                'is_active' => true
            ]);
            echo "✅ Created Alpine host\n";
        } else {
            echo "ℹ️  Alpine host already exists\n";
        }

        // $ubuntuHost = Host::create([
        //     'host_name' => 'ubuntu-test-host',
        //     'ip_address' => $dockerNetwork . '.2',
        //     'description' => 'Ubuntu Linux test container',
        //     'operating_system' => 'Ubuntu 22.04 LTS',
        //     'agent_version' => '2.0',
        //     'is_active' => true
        // ]);

        $ubuntuHost = Host::where('ip_address', $dockerNetwork . '.2')->first();
        if (!$ubuntuHost) {
            $ubuntuHost = Host::create([
                'host_name' => 'ubuntu-test-host',
                'ip_address' => $dockerNetwork . '.2',
                'description' => 'Ubuntu Linux test container',
                'operating_system' => 'Ubuntu 22.04 LTS',
                'agent_version' => '2.0',
                'is_active' => true
            ]);
            echo "✅ Created Ubuntu host\n";
        } else {
            echo "ℹ️  Ubuntu host already exists\n";
        }

        // $rockyHost = Host::create([
        //     'host_name' => 'rocky-test-host',
        //     'ip_address' => $dockerNetwork . '.4',
        //     'description' => 'Rocky Linux test container',
        //     'operating_system' => 'Rocky Linux 9',
        //     'agent_version' => '2.0',
        //     'is_active' => true
        // ]);

        $rockyHost = Host::where('ip_address', $dockerNetwork . '.4')->first();
        if (!$rockyHost) {
            $rockyHost = Host::create([
                'host_name' => 'rocky-test-host',
                'ip_address' => $dockerNetwork . '.4',
                'description' => 'Rocky Linux test container',
                'operating_system' => 'Rocky Linux 9',
                'agent_version' => '2.0',
                'is_active' => true
            ]);
            echo "✅ Created Rocky host\n";
        } else {
            echo "ℹ️  Rocky host already exists\n";
        }

        echo "✅ All hosts created\n";
        echo "🔧 Creating host configurations...\n";

        // 4. Konfiguracja hostów
        // HostConfiguration::create([
        //     'host_id' => $localHost->host_id,
        //     'updated_by_user_id' => $admin->id
        // ]);

        $localConfig = HostConfiguration::where('host_id', $localHost->host_id)->first();
        if (!$localConfig) {
            HostConfiguration::create([
                'host_id' => $localHost->host_id,
                'updated_by_user_id' => $admin->id
            ]);
            echo "✅ Created local host configuration\n";
        } else {
            echo "ℹ️  Local host configuration already exists\n";
        }
        
        // HostConfiguration::create([
        //     'host_id' => $alpineHost->host_id,
        //     'updated_by_user_id' => $admin->id
        // ]);

        $alpineConfig = HostConfiguration::where('host_id', $alpineHost->host_id)->first();
        if (!$alpineConfig) {
            HostConfiguration::create([
                'host_id' => $alpineHost->host_id,
                'updated_by_user_id' => $admin->id
            ]);
            echo "✅ Created Alpine host configuration\n";
        } else {
            echo "ℹ️  Alpine host configuration already exists\n";
        }

        // HostConfiguration::create([
        //     'host_id' => $ubuntuHost->host_id,
        //     'updated_by_user_id' => $admin->id
        // ]);

        $ubuntuConfig = HostConfiguration::where('host_id', $ubuntuHost->host_id)->first();
        if (!$ubuntuConfig) {
            HostConfiguration::create([
                'host_id' => $ubuntuHost->host_id,
                'updated_by_user_id' => $admin->id
            ]);
            echo "✅ Created Ubuntu host configuration\n";
        } else {
            echo "ℹ️  Ubuntu host configuration already exists\n";
        }

        // HostConfiguration::create([
        //     'host_id' => $rockyHost->host_id,
        //     'updated_by_user_id' => $admin->id
        // ]);

        $rockyConfig = HostConfiguration::where('host_id', $rockyHost->host_id)->first();
        if (!$rockyConfig) {
            HostConfiguration::create([
                'host_id' => $rockyHost->host_id,
                'updated_by_user_id' => $admin->id
            ]);
            echo "✅ Created Rocky host configuration\n";
        } else {
            echo "ℹ️  Rocky host configuration already exists\n";
        }

        echo "✅ Host configurations created\n";

        // 5. Katalogi do monitorowania - dynamiczne na podstawie systemu lokalnego
        echo "📁 Creating monitored directories...\n";
        // $monitoredDirectories = [];
        
        // Dla hosta lokalnego - dobierz katalogi na podstawie systemu
        // $localDirectories = $this->getDirectoriesForSystem($localSystem);
        // foreach ($localDirectories as $directory) {
        //     $monitoredDirectories[] = [
        //         'host_id' => $localHost->host_id,
        //         'directory_path' => $directory,
        //         'is_active' => true
        //     ];
        // }

        $localDirectories = $this->getDirectoriesForSystem($localSystem);
        foreach ($localDirectories as $directory) {
            $existing = MonitoredDirectory::where('host_id', $localHost->host_id)
                                         ->where('directory_path', $directory)
                                         ->first();
            if (!$existing) {
                MonitoredDirectory::create([
                    'host_id' => $localHost->host_id,
                    'directory_path' => $directory,
                    'is_active' => true
                ]);
                echo "✅ Created directory: {$directory} for local host\n";
            } else {
                echo "ℹ️  Directory already exists: {$directory} for local host\n";
            }
        }
        
        // Dla hostów Linux (kontenery Docker) - standardowe katalogi Linuxowe
        $defaultLinuxDirectories = ['/root', '/var', '/tmp', '/home', '/usr'];
        $linuxHosts = [$alpineHost, $ubuntuHost, $rockyHost];

        // foreach ($linuxHosts as $host) {
        //     foreach ($defaultLinuxDirectories as $directory) {
        //         $monitoredDirectories[] = [
        //             'host_id' => $host->host_id,
        //             'directory_path' => $directory,
        //             'is_active' => true
        //         ];
        //     }
        // }

         $defaultLinuxDirectories = ['/root', '/var', '/tmp', '/home', '/usr'];
        $linuxHosts = [$alpineHost, $ubuntuHost, $rockyHost];

        foreach ($linuxHosts as $host) {
            foreach ($defaultLinuxDirectories as $directory) {
                $existing = MonitoredDirectory::where('host_id', $host->host_id)
                                             ->where('directory_path', $directory)
                                             ->first();
                if (!$existing) {
                    MonitoredDirectory::create([
                        'host_id' => $host->host_id,
                        'directory_path' => $directory,
                        'is_active' => true
                    ]);
                    echo "✅ Created directory: {$directory} for {$host->host_name}\n";
                } else {
                    echo "ℹ️  Directory already exists: {$directory} for {$host->host_name}\n";
                }
            }
        }

        echo "✅ Monitored directories processed\n";

        // foreach ($monitoredDirectories as $dir) {
        //     MonitoredDirectory::create($dir);
        // }

        // 6. Progi alertów globalne - podstawowe metryki
        AlertThreshold::create([
            'host_id' => null, // Global
            'metric_type_id' => $cpuType->metric_type_id,
            'warning_threshold' => 70.0,
            'critical_threshold' => 90.0,
            'created_by_user_id' => $admin->id
        ]);

        AlertThreshold::create([
            'host_id' => null, // Global
            'metric_type_id' => $ramType->metric_type_id,
            'warning_threshold' => 80.0,
            'critical_threshold' => 95.0,
            'created_by_user_id' => $admin->id
        ]);

        AlertThreshold::create([
            'host_id' => null, // Global
            'metric_type_id' => $networkType->metric_type_id,
            'warning_threshold' => 1000.0, // 1 second
            'critical_threshold' => 5000.0, // 5 seconds
            'created_by_user_id' => $admin->id
        ]);

        // 7. Progi alertów globalne - Storage/Dyski (ID 4-53)
        foreach ($storageTypes as $storageType) {
            AlertThreshold::create([
                'host_id' => null, // Global threshold for all hosts
                'metric_type_id' => $storageType->metric_type_id,
                'warning_threshold' => 80.0, // 80% disk usage = warning
                'critical_threshold' => 90.0, // 90% disk usage = critical
                'created_by_user_id' => $admin->id
            ]);
        }

        // 8. Przykładowe progi alertów specyficzne dla hosta Windows (dysk C:)
        // Dysk C: to typowo Storage-4 (pierwsze Storage ID)
        // AlertThreshold::create([
        //     'host_id' => $windowsHost->host_id, // Host-specific threshold
        //     'metric_type_id' => 4, // Storage-4 (pierwszy slot storage)
        //     'warning_threshold' => 70.0, // Bardziej restrykcyjny próg dla dysku C:
        //     'critical_threshold' => 90.0, // Bardziej restrykcyjny próg dla dysku C:
        //     'created_by_user_id' => $admin->id
        // ]);

        // 9. Przykładowe progi alertów specyficzne dla katalogów Linux
        // /var to krytyczny katalog - bardziej restrykcyjne progi
        AlertThreshold::create([
            'host_id' => $alpineHost->host_id, // Alpine host specific
            'metric_type_id' => 5, // Storage-5 (drugi slot storage dla /var)
            'warning_threshold' => 75.0, // Bardziej restrykcyjny dla /var
            'critical_threshold' => 90.0, // Bardziej restrykcyjny dla /var
            'created_by_user_id' => $admin->id
        ]);

        AlertThreshold::create([
            'host_id' => $ubuntuHost->host_id, // Ubuntu host specific
            'metric_type_id' => 5, // Storage-5 (drugi slot storage dla /var)
            'warning_threshold' => 75.0, // Bardziej restrykcyjny dla /var
            'critical_threshold' => 90.0, // Bardziej restrykcyjny dla /var
            'created_by_user_id' => $admin->id
        ]);

        // 10. Dodatkowe progi alertów dla pozostałych hostów Linux
        AlertThreshold::create([
            'host_id' => $rockyHost->host_id, // Rocky host specific
            'metric_type_id' => 4, // Storage-4 (główny system plików)
            'warning_threshold' => 85.0, // Mniej restrykcyjny dla głównego FS
            'critical_threshold' => 95.0,
            'created_by_user_id' => $admin->id
        ]);

        // Informacja końcowa o seedingu
        echo "\n🎉 ZenMon Seeder completed successfully!\n";
        echo "\n=== SUMMARY ===\n";
        echo "System users:\n";
        echo "- Admin: admin / admin123 (Administrator role)\n";
        echo "- Agent: zenmon_agent / zenmon_agent123 (Agent role)\n";
        echo "- Test users: 5 created with Factory (User role)\n";
        echo "\nComponents:\n";
        echo "- 3 basic metric types (CPU, RAM, Network)\n";
        echo "- 50 storage metric types (Storage-4 to Storage-53)\n";
        echo "- 4 hosts:\n";
        echo "  * Local host: {$localHost->host_name} ({$localSystem})\n";
        echo "  * 3 Docker container hosts (Alpine, Ubuntu, Rocky)\n";
        echo "- Host configurations: 4 created\n";
        echo "- Monitored directories: Linux directories created\n";
        echo "- Alert thresholds: Global + host-specific created\n";
        echo "- Storage alert thresholds: 80% warning, 90% critical\n";
        echo "\nNetwork:\n";
        echo "- Docker network base: {$dockerNetwork}.x\n";
        echo "- System detected: {$localSystem}\n";
        
        if (!empty($localDirectories)) {
            echo "- Local host directories: " . implode(', ', $localDirectories) . "\n";
        } else {
            echo "- Local host: Windows (uses drives automatically)\n";
        }
        
        echo "\n✅ Ready to test alerting system!\n";
        echo "🤖 All agents should use: zenmon_agent / zenmon_agent123\n";
        echo "👤 Test users have pattern: [login]123\n";
    }

    #endregion

    #region Private Methods

    /// <summary>
    /// Detect operating system dynamically
    /// </summary>
    /// <returns>string</returns>
    private function detectOperatingSystem(): string
    {
        $os = PHP_OS_FAMILY;
        
        switch ($os) {
            case 'Windows':
                // Try to get Windows version
                $version = shell_exec('ver 2>nul');
                if ($version && preg_match('/Windows\s+([^\]]+)/', $version, $matches)) {
                    return 'Windows ' . trim($matches[1]);
                }
                return 'Windows';
                
            case 'Linux':
                // Try to get Linux distribution
                if (file_exists('/etc/os-release')) {
                    $osRelease = file_get_contents('/etc/os-release');
                    if (preg_match('/PRETTY_NAME="([^"]+)"/', $osRelease, $matches)) {
                        return $matches[1];
                    }
                    if (preg_match('/NAME="([^"]+)"/', $osRelease, $matches)) {
                        return $matches[1];
                    }
                }
                return 'Linux';
                
            case 'Darwin':
                // macOS version detection
                $version = shell_exec('sw_vers -productVersion 2>/dev/null');
                return 'macOS ' . ($version ? trim($version) : '');
                
            case 'BSD':
                return 'BSD Unix';
                
            default:
                return $os;
        }
    }

    /// <summary>
    /// Generate host description based on system
    /// </summary>
    /// <param>string $system</param>
    /// <returns>string</returns>
    private function generateHostDescription(string $system): string
    {
        $descriptions = [
            'Windows' => 'Local Windows development machine',
            'Linux' => 'Local Linux development machine', 
            'macOS' => 'Local macOS development machine',
            'BSD' => 'Local BSD development machine'
        ];
        
        foreach ($descriptions as $os => $desc) {
            if (stripos($system, $os) !== false) {
                return $desc;
            }
        }
        
        return 'Local development machine';
    }

    /// <summary>
    /// Get directories to monitor based on operating system
    /// </summary>
    /// <param>string $system</param>
    /// <returns>array</returns>
    private function getDirectoriesForSystem(string $system): array
    {
        if (stripos($system, 'Windows') !== false) {
            // Windows - agent używa dysków automatycznie, więc nie tworzymy katalogów
            return [];
        } else {
            // Linux/macOS/Unix - standardowe katalogi
            return ['/root', '/var', '/tmp', '/home', '/usr'];
        }
    }

    /// <summary>
    /// Get Docker network base dynamically
    /// </summary>
    /// <returns>string</returns>
    private function getDockerNetworkBase(): string
    {
        // Try to detect Docker network, fallback to common default
        $networks = ['172.19.0', '172.18.0', '172.17.0', '192.168.1'];
        
        // Check if docker is available and try to get network info
        $dockerInfo = shell_exec('docker network ls 2>/dev/null');
        if ($dockerInfo && stripos($dockerInfo, 'zenmon') !== false) {
            // If zenmon network exists, try to inspect it
            $networkInfo = shell_exec('docker network inspect zenmon 2>/dev/null');
            if ($networkInfo) {
                $data = json_decode($networkInfo, true);
                if (isset($data[0]['IPAM']['Config'][0]['Subnet'])) {
                    $subnet = $data[0]['IPAM']['Config'][0]['Subnet'];
                    if (preg_match('/(\d+\.\d+\.\d+)\./', $subnet, $matches)) {
                        return $matches[1];
                    }
                }
            }
        }
        
        return $networks[0]; // Default fallback
    }

    /// <summary>
    /// Get storage thresholds based on local system
    /// </summary>
    /// <param>string $system</param>
    /// <returns>array</returns>
    private function getLocalStorageThresholds(string $system): array
    {
        if (stripos($system, 'Windows') !== false) {
            // Windows C: drive - same as global but could be customized
            return [
                'warning' => 80.0,
                'critical' => 90.0
            ];
        } else {
            // Linux/macOS root filesystem - same as global but could be customized
            return [
                'warning' => 80.0,
                'critical' => 90.0
            ];
        }
    }

    /// <summary>
    /// Create test users using factory
    /// </summary>
    /// <param>int $count</param>
    /// <returns>void</returns>
    private function createTestUsers(int $count): void
    {
        if ($count < 1 || $count > 100) {
            // $this->command->error("User count must be between 1 and 100. Provided: {$count}");
            echo "Error: User count must be between 1 and 100. Provided: {$count}\n";
            return;
        }

        echo "Creating {$count} test users...\n";

        $this->command->info("Creating {$count} test users...");

        $testUsers = User::factory()
                        ->count($count)
                        ->regularUser()
                        ->recentlyLoggedIn()
                        ->create();

        $this->command->info("✅ Created {$count} test users:");
        foreach ($testUsers as $index => $user) {
            $this->command->line("  {$user->login} / {$user->login}123 ({$user->first_name} {$user->last_name})");
        }

        $this->command->newLine();
        $this->command->info("💡 Password pattern: [login]123");
        $this->command->info("🤖 Agent credentials: zenmon_agent / zenmon_agent123");
    }

    #endregion
}