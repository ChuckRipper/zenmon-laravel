<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Seeder for creating single test user
 * Run multiple times to create multiple users
 */
class SingleUserSeeder extends Seeder
{
    /// <summary>
    /// Run the seeder - creates exactly 1 user
    /// </summary>
    /// <returns>void</returns>
    public function run(): void
    {
        $this->command->info("Creating 1 test user...");

        // Create single user using factory
        $user = User::factory()
                   ->regularUser()
                   ->recentlyLoggedIn()
                   ->create();

        // Display created user
        $this->command->info("✅ Successfully created test user:");
        $this->command->line(sprintf(
            "  Login: %s / Password: %s123",
            $user->login,
            $user->login
        ));
        $this->command->line(sprintf(
            "  Name: %s %s",
            $user->first_name,
            $user->last_name
        ));
        $this->command->line(sprintf(
            "  Email: %s",
            $user->email
        ));
        $this->command->line(sprintf(
            "  Role: %s",
            $user->role
        ));

        $this->command->newLine();
        $this->command->info("🔄 Run again to create another user!");
    }
}