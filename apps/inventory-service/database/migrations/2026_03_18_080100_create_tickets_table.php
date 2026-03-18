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
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('seat_number');
            $table->decimal('price', 10, 2);
            $table->string('status', 20)->default('available');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestampTz('reserved_until')->nullable();
            $table->timestampsTz();

            $table->unique(['event_id', 'seat_number']);
            $table->index(['event_id', 'status']);
            $table->index(['status', 'reserved_until']);
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
