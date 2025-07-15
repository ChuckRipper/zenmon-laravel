<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name', 50)->after('email');
            $table->string('last_name', 50)->after('first_name'); 
            // $table->enum('role', ['Administrator', 'User'])->default('User')->after('last_name');
            $table->enum('role', ['Administrator', 'Agent', 'User'])->default('User')->after('last_name');
            $table->boolean('is_active')->default(true)->after('role');
            $table->datetime('last_login_date')->nullable()->after('is_active');
            
            // Rename existing columns to match UML
            $table->renameColumn('name', 'login'); // name -> login
        });
    }

        /**
         * Reverse the migrations.
         */
        public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Usuń dodane kolumny
            $table->dropColumn(['first_name', 'last_name', 'role', 'is_active', 'last_login_date']);
            
            // Przywróć oryginalną nazwę kolumny
            $table->renameColumn('login', 'name');
        });
    }
};