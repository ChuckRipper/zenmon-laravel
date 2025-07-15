<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /// <summary>
    /// Run the migrations - add Agent role to users table
    /// </summary>
    /// <returns>void</returns>
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // BEZPIECZNE: rozszerza enum, nie usuwa istniejących wartości
            $table->enum('role', ['Administrator', 'Agent', 'User'])
                  ->default('User')
                  ->change();
        });
    }

    /// <summary>
    /// Reverse the migrations
    /// </summary>
    /// <returns>void</returns>
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Rollback: przywraca poprzedni stan
            $table->enum('role', ['Administrator', 'User'])
                  ->default('User')
                  ->change();
        });
    }
};