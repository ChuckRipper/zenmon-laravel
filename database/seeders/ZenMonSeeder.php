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
    public function run(): void
    {
        // 1. Admin user zgodnie z dokumentacją UML
        $admin = User::create([
            'login' => 'admin',
            'password' => Hash::make('admin123'),
            'email' => 'admin@zenmon.local',
            'first_name' => 'System',
            'last_name' => 'Administrator',
            'role' => 'Administrator'
        ]);

        // 2. Podstawowe typy metryk - NOWA KOLEJNOŚĆ
        $cpuType = MetricType::create([
            'metric_name' => 'CPU',
            'unit' => '%',
            'description' => 'Wykorzystanie procesora'
        ]);

        $ramType = MetricType::create([
            'metric_name' => 'RAM',
            'unit' => '%', 
            'description' => 'Wykorzystanie pamięci RAM'
        ]);

        $networkType = MetricType::create([
            'metric_name' => 'Network',
            'unit' => 'ms',
            'description' => 'Czas odpowiedzi sieci'
        ]);

        // 2.1. Storage types (50 slotów na dyski Windows / katalogi Linux)
        for ($i = 4; $i <= 53; $i++) {
            MetricType::create([
                'metric_name' => "Storage-{$i}",
                'unit' => '%',
                'description' => "Disk/Directory usage slot {$i}"
            ]);
        }

        // 3. Hosty testowe
        $windowsHost = Host::create([
            'host_name' => 'DESKTOP-FIUVQK4',
            'ip_address' => '127.0.0.1',
            'description' => 'Windows development machine',
            'operating_system' => 'Windows 11',
            'agent_version' => '2.0',
            'is_active' => true
        ]);

        $alpineHost = Host::create([
            'host_name' => 'alpine-test-host',
            'ip_address' => '172.19.0.3',
            'description' => 'Alpine Linux test container',
            'operating_system' => 'Alpine Linux 3.18',
            'agent_version' => '2.0',
            'is_active' => true
        ]);

        $ubuntuHost = Host::create([
            'host_name' => 'ubuntu-test-host',
            'ip_address' => '172.19.0.2',
            'description' => 'Ubuntu Linux test container',
            'operating_system' => 'Ubuntu 22.04 LTS',
            'agent_version' => '2.0',
            'is_active' => true
        ]);

        $rockyHost = Host::create([
            'host_name' => 'rocky-test-host',
            'ip_address' => '172.19.0.4',
            'description' => 'Rocky Linux test container',
            'operating_system' => 'Rocky Linux 9',
            'agent_version' => '2.0',
            'is_active' => true
        ]);

        // 4. Konfiguracja hostów
        HostConfiguration::create([
            'host_id' => $windowsHost->host_id,
            'updated_by_user_id' => $admin->id
        ]);

        HostConfiguration::create([
            'host_id' => $alpineHost->host_id,
            'updated_by_user_id' => $admin->id
        ]);

        HostConfiguration::create([
            'host_id' => $ubuntuHost->host_id,
            'updated_by_user_id' => $admin->id
        ]);

        HostConfiguration::create([
            'host_id' => $rockyHost->host_id,
            'updated_by_user_id' => $admin->id
        ]);

        // 5. Katalogi do monitorowania dla hostów Linux
        // (Windows będzie używać Storage-X dla dysków automatycznie)
        // Zgodnie z fallback w agencie: ['/root', '/var', '/tmp', '/home', '/usr']
        $defaultLinuxDirectories = ['/root', '/var', '/tmp', '/home', '/usr'];
        $linuxHosts = [$alpineHost, $ubuntuHost, $rockyHost];

        $monitoredDirectories = [];
        foreach ($linuxHosts as $host) {
            foreach ($defaultLinuxDirectories as $directory) {
                $monitoredDirectories[] = [
                    'host_id' => $host->host_id,
                    'directory_path' => $directory,
                    'is_active' => true
                ];
            }
        }

        foreach ($monitoredDirectories as $dir) {
            MonitoredDirectory::create($dir);
        }

        // 6. Progi alertów globalne
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
    }
}