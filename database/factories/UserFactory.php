<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    #region Properties
    
    /// <summary>
    /// Define the model's default state for ZenMon users
    /// </summary>
    /// <returns>array<string, mixed></returns>
    public function definition(): array
    {
        $firstName = fake()->firstName();
        $lastName = fake()->lastName();
        $login = strtolower($firstName . '.' . $lastName);
        
        return [
            'login' => $login,
            'email' => fake()->unique()->safeEmail(),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'role' => 'User', // Default role
            'password' => Hash::make($login . '123'), // Pattern: login + "123"
            'is_active' => true,
            'last_login_date' => null
        ];
    }
    
    #endregion

    #region State Methods

    /// <summary>
    /// Create user with Administrator role
    /// </summary>
    /// <returns>static</returns>
    public function administrator(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'Administrator',
            'email' => str_replace('@', '+admin@', $attributes['email'])
        ]);
    }

    /// <summary>
    /// Create user with Agent role
    /// </summary>
    /// <returns>static</returns>
    public function agent(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'Agent',
            'first_name' => 'Agent',
            'last_name' => 'System',
            'login' => 'agent_' . Str::random(6),
            'email' => str_replace('@', '+agent@', $attributes['email'])
        ]);
    }

    /// <summary>
    /// Create user with User role (default, but explicit)
    /// </summary>
    /// <returns>static</returns>
    public function regularUser(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'User'
        ]);
    }

    /// <summary>
    /// Create inactive user
    /// </summary>
    /// <returns>static</returns>
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false
        ]);
    }

    /// <summary>
    /// Create user with recent login
    /// </summary>
    /// <returns>static</returns>
    public function recentlyLoggedIn(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_login_date' => fake()->dateTimeBetween('-30 days', 'now')
        ]);
    }
    
    #endregion
}