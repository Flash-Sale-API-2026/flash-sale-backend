<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inbox_messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_id');
            $table->string('event_type');
            $table->jsonb('payload');
            $table->string('status', 20);
            $table->text('failure_reason')->nullable();
            $table->timestampTz('processed_at')->nullable();
            $table->timestampsTz();

            $table->unique('event_id');
            $table->index(['event_type', 'status']);
            $table->index('processed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbox_messages');
    }
};
