<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\User;

/**
 * Seeder for creating test users with User role
 * Can be run multiple times with different parameters
 */
class TestUsersSeeder extends Seeder
{
    /// <summary>
    /// Run the seeder
    /// </summary>
    /// <param>int $count Number of users to create (default: 5)</param>
    /// <returns>void</returns>
    public function run(): void
    {
        $userCount = 5; // Fixed count for simplicity
        
        echo "Creating {$userCount} test users...\n";

        // Create users using factory
        $users = User::factory()
                    ->count($userCount)
                    ->regularUser()
                    ->recentlyLoggedIn()
                    ->create();

        // Display created users
        // $this->command->info("✅ Successfully created {$userCount} test users:");
        echo "✅ Successfully created {$userCount} test users:\n";
        
        foreach ($users as $index => $user) {
            echo sprintf(
                "  %d. %s / %s123 (%s %s, %s)\n",
                $index + 1,
                $user->login,
                $user->login,
                $user->first_name,
                $user->last_name,
                $user->role
            );
        }

        // $this->command->newLine();
        // $this->command->info("💡 All passwords follow pattern: [login]123");
        // $this->command->info("🔄 Run again to create more users!");\
        echo "\n💡 All passwords follow pattern: [login]123\n";
        echo "🔄 Run again to create more users!\n";
    }
}
