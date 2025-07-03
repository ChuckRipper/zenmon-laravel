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
        Schema::create('hosts', function (Blueprint $table) {
            $table->id('host_id'); // HostID - PK
            $table->string('host_name', 100); // HostName
            $table->string('ip_address', 45)->unique(); // IPAddress - UNIQUE (IPv4/IPv6)
            $table->string('description', 500)->nullable(); // Description
            $table->string('operating_system', 100)->nullable(); // OperatingSystem
            $table->string('agent_version', 20)->nullable(); // AgentVersion
            $table->boolean('is_active')->default(true); // IsActive
            $table->datetime('last_contact_date')->nullable(); // LastContactDate
            $table->timestamps(); // created_at (CreatedDate), updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hosts');
    }
};