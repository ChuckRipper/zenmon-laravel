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
        Schema::create('alert_thresholds', function (Blueprint $table) {
            $table->id('threshold_id'); // ThresholdID - PK
            $table->unsignedBigInteger('host_id')->nullable(); // HostID - FK (NULL = próg globalny)
            $table->unsignedBigInteger('metric_type_id'); // MetricTypeID - FK
            $table->decimal('warning_threshold', 15, 4); // WarningThreshold
            $table->decimal('critical_threshold', 15, 4); // CriticalThreshold
            $table->boolean('is_active')->default(true); // IsActive
            $table->unsignedBigInteger('created_by_user_id'); // CreatedByUserID - FK
            $table->timestamps(); // created_at (CreatedDate), updated_at
            
            // Indeksy (zgodnie z dokumentacją UML)
            $table->index(['host_id', 'metric_type_id']);
            $table->index(['metric_type_id', 'is_active']);
            $table->index('created_by_user_id');
            
            // Foreign keys
            $table->foreign('host_id')->references('host_id')->on('hosts')->onDelete('cascade');
            $table->foreign('metric_type_id')->references('metric_type_id')->on('metric_types')->onDelete('restrict');
            $table->foreign('created_by_user_id')->references('id')->on('users')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alert_thresholds');
    }
};
