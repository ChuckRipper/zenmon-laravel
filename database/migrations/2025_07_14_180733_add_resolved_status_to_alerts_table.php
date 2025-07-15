<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /// <summary>
    /// Run the migrations - add Resolved status to alerts table
    /// </summary>
    /// <returns>void</returns>
    public function up(): void
    {
        Schema::table('alerts', function (Blueprint $table) {
            // BEZPIECZNE: rozszerza enum, nie usuwa istniejących wartości
            $table->enum('status', ['Active', 'Acknowledged', 'Closed', 'Resolved'])
                ->default('Active')
                ->change();
        });
    }

    /// <summary>
    /// Reverse the migrations
    /// </summary>
    /// <returns>void</returns>
    public function down(): void
    {
        Schema::table('alerts', function (Blueprint $table) {
            // Rollback: przywraca poprzedni stan
            $table->enum('status', ['Active', 'Acknowledged', 'Closed'])
                ->default('Active')
                ->change();
        });
    }
};
