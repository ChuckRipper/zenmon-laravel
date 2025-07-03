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
        Schema::create('connection_statuses', function (Blueprint $table) {
            $table->bigIncrements('status_id'); // StatusID - BIGINT PK
            $table->unsignedBigInteger('host_id'); // HostID - FK
            $table->enum('status', ['Online', 'Offline', 'Unknown']); // Status
            $table->integer('response_time')->nullable()->comment('milliseconds'); // ResponseTime
            $table->datetime('last_check_date')->useCurrent(); // LastCheckDate
            $table->string('error_message', 500)->nullable(); // ErrorMessage
            
            // Indeksy (zgodnie z dokumentacją UML)
            $table->index(['host_id', 'last_check_date']);
            $table->index(['status', 'last_check_date']);
            $table->index('last_check_date');
            
            // Foreign key
            $table->foreign('host_id')->references('host_id')->on('hosts')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('connection_statuses');
    }
};