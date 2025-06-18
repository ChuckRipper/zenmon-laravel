<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\{User, MetricType, Host, HostConfiguration, AlertThreshold};
use Illuminate\Support\Facades\Hash;

class ZenMonSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Admin user zgodnie z dokumentacją UML
        $admin = User::create([
            'login' => 'admin',
            'password' => Hash::make('admin123'), // SHA-256 w produkcji
            'email' => 'admin@zenmon.local',
            'first_name' => 'System',
            'last_name' => 'Administrator',
            'role' => 'Administrator'
        ]);

        // 2. Podstawowe typy metryk z dokumentacji UML
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

        $diskType = MetricType::create([
            'metric_name' => 'Disk',
            'unit' => '%',
            'description' => 'Wykorzystanie przestrzeni dyskowej'
        ]);

        $networkType = MetricType::create([
            'metric_name' => 'Network',
            'unit' => 'ms',
            'description' => 'Czas odpowiedzi sieci'
        ]);

        // 3. Przykładowy host
        $host = Host::create([
            'host_name' => 'localhost',
            'ip_address' => '127.0.0.1',
            'description' => 'Test localhost server',
            'operating_system' => 'Windows 11',
            'agent_version' => '1.0.0'
        ]);

        // 4. Konfiguracja hosta
        HostConfiguration::create([
            'host_id' => $host->host_id,
            'updated_by_user_id' => $admin->id
        ]);

        // 5. Progi alertów globalne
        AlertThreshold::create([
            'host_id' => null, // Global
            'metric_type_id' => $cpuType->metric_type_id,
            'warning_threshold' => 70.0,
            'critical_threshold' => 90.0,
            'created_by_user_id' => $admin->id
        ]);
    }
}
