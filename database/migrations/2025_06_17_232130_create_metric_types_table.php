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
        Schema::create('metric_types', function (Blueprint $table) {
            $table->id('metric_type_id'); // MetricTypeID - PK
            $table->string('metric_name', 50)->unique(); // MetricName - UNIQUE
            $table->string('unit', 10); // Unit
            $table->string('description', 200)->nullable(); // Description
            $table->timestamps(); // created_at, updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('metric_types');
    }
};
