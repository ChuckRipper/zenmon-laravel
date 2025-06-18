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
        Schema::create('user_sessions', function (Blueprint $table) {
            $table->id('session_id'); // SessionID - PK
            $table->unsignedBigInteger('user_id'); // UserID - FK
            $table->string('session_token', 255)->unique(); // SessionToken - UNIQUE
            $table->datetime('login_date')->useCurrent(); // LoginDate
            $table->datetime('last_activity_date')->useCurrent(); // LastActivityDate
            $table->string('ip_address', 45); // IPAddress (IPv4/IPv6)
            $table->boolean('is_active')->default(true); // IsActive
            
            // Indeksy (zgodnie z dokumentacją UML)
            $table->index(['user_id', 'is_active']);
            $table->index('last_activity_date');
            
            // Foreign key
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_sessions');
    }
};
