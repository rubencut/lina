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
        Schema::create('exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exported_by')->constrained('users')->restrictOnDelete();
            $table->string('type');
            $table->enum('format', ['Excel', 'PDF', 'CSV']);
            $table->string('file_path')->nullable();
            $table->json('filters')->nullable();
            $table->timestamp('created_at')->useCurrent();
            
            $table->index('exported_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exports');
    }
};
