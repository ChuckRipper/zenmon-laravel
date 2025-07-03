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
        Schema::create('host_configurations', function (Blueprint $table) {
            $table->id('configuration_id'); // ConfigurationID - PK
            $table->unsignedBigInteger('host_id')->unique(); // HostID - FK (UNIQUE = relacja 1:1)
            $table->integer('data_collection_interval')->default(120); // DataCollectionInterval (seconds)
            $table->boolean('enable_cpu_monitoring')->default(true); // EnableCPUMonitoring
            $table->boolean('enable_ram_monitoring')->default(true); // EnableRAMMonitoring  
            $table->boolean('enable_disk_monitoring')->default(true); // EnableDiskMonitoring
            $table->boolean('enable_network_monitoring')->default(true); // EnableNetworkMonitoring
            $table->unsignedBigInteger('updated_by_user_id'); // UpdatedByUserID - FK
            $table->timestamps(); // created_at, updated_at (LastUpdated)
            
            // Foreign keys
            $table->foreign('host_id')->references('host_id')->on('hosts')->onDelete('cascade');
            $table->foreign('updated_by_user_id')->references('id')->on('users')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('host_configurations');
    }
};