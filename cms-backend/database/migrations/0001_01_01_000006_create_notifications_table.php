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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('type', ['email', 'sms'])->default('email');
            $table->text('message');
            $table->enum('status', ['pending', 'sent', 'failed', 'read'])->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            
            $table->index('user_id');
            $table->index('type');
            $table->index('status');
            $table->index('sent_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
