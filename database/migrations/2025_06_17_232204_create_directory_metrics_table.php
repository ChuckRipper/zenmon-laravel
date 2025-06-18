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
        Schema::create('directory_metrics', function (Blueprint $table) {
            $table->bigIncrements('directory_metric_id'); // DirectoryMetricID - BIGINT PK
            $table->unsignedBigInteger('directory_id'); // DirectoryID - FK
            $table->bigInteger('used_space'); // UsedSpace - BIGINT (bytes)
            $table->bigInteger('total_space'); // TotalSpace - BIGINT (bytes)
            $table->bigInteger('available_space'); // AvailableSpace - BIGINT (bytes)
            $table->integer('file_count'); // FileCount - INT
            $table->datetime('timestamp'); // Timestamp - DATETIME
            
            // Indeksy dla wydajności (zgodnie z dokumentacją UML)
            $table->index(['directory_id', 'timestamp']);
            $table->index('timestamp');
            
            // Foreign key
            $table->foreign('directory_id')->references('directory_id')->on('monitored_directories')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('directory_metrics');
    }
};
