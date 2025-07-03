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
        Schema::create('monitored_directories', function (Blueprint $table) {
            $table->id('directory_id'); // DirectoryID - PK
            $table->unsignedBigInteger('host_id'); // HostID - FK
            $table->string('directory_path', 500); // DirectoryPath
            $table->boolean('is_active')->default(true); // IsActive
            $table->timestamps(); // created_at (CreatedDate), updated_at
            
            // Unique constraint (HostID, DirectoryPath)
            $table->unique(['host_id', 'directory_path']);
            
            // Indeksy
            $table->index('host_id');
            $table->index('is_active');
            
            // Foreign key
            $table->foreign('host_id')->references('host_id')->on('hosts')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monitored_directories');
    }
};