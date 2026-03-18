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
        Schema::create('outbox_messages', function (Blueprint $table) {
            $table->id();
            $table->string('aggregate_type');
            $table->unsignedBigInteger('aggregate_id');
            $table->string('type');
            $table->jsonb('payload');
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('processed_at')->nullable();

            $table->index(['aggregate_type', 'aggregate_id']);
            $table->index(['processed_at', 'created_at']);
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('outbox_messages');
    }
};
