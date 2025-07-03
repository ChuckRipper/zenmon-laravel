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
        Schema::create('metrics', function (Blueprint $table) {
            $table->bigIncrements('metric_id'); // MetricID - BIGINT PK
            $table->unsignedBigInteger('host_id'); // HostID - FK
            $table->unsignedBigInteger('metric_type_id'); // MetricTypeID - FK
            $table->decimal('value', 15, 4); // Value - DECIMAL(15,4)
            $table->datetime('timestamp'); // Timestamp - DATETIME
            $table->json('additional_info')->nullable(); // AdditionalInfo - JSON
            
            // Indeksy dla wydajności (zgodnie z dokumentacją UML)
            $table->index(['host_id', 'timestamp']);
            $table->index(['metric_type_id', 'timestamp']);
            $table->index(['host_id', 'metric_type_id', 'timestamp']);
            $table->index('timestamp');
            
            // Foreign keys
            $table->foreign('host_id')->references('host_id')->on('hosts')->onDelete('cascade');
            $table->foreign('metric_type_id')->references('metric_type_id')->on('metric_types')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('metrics');
    }
};