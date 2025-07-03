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
        Schema::create('alerts', function (Blueprint $table) {
            $table->bigIncrements('alert_id'); // AlertID - BIGINT PK
            $table->unsignedBigInteger('host_id'); // HostID - FK
            $table->unsignedBigInteger('metric_type_id'); // MetricTypeID - FK
            $table->enum('alert_level', ['Warning', 'Critical']); // AlertLevel
            $table->string('alert_message', 1000); // AlertMessage
            $table->decimal('current_value', 15, 4); // CurrentValue
            $table->decimal('threshold_value', 15, 4); // ThresholdValue
            $table->enum('status', ['Active', 'Acknowledged', 'Closed'])->default('Active'); // Status
            $table->datetime('acknowledged_date')->nullable(); // AcknowledgedDate
            $table->unsignedBigInteger('acknowledged_by_user_id')->nullable(); // AcknowledgedByUserID - FK
            $table->datetime('closed_date')->nullable(); // ClosedDate
            $table->unsignedBigInteger('closed_by_user_id')->nullable(); // ClosedByUserID - FK
            $table->string('close_comment', 1000)->nullable(); // CloseComment
            $table->timestamps(); // created_at (CreatedDate), updated_at
            
            // Indeksy dla dashboardu (zgodnie z dokumentacją UML)
            $table->index(['host_id', 'status', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index(['metric_type_id', 'created_at']);
            $table->index('created_at');
            $table->index('acknowledged_by_user_id');
            $table->index('closed_by_user_id');
            
            // Foreign keys
            $table->foreign('host_id')->references('host_id')->on('hosts')->onDelete('cascade');
            $table->foreign('metric_type_id')->references('metric_type_id')->on('metric_types')->onDelete('restrict');
            $table->foreign('acknowledged_by_user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('closed_by_user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};