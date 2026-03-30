<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('outbox_messages', 'outbox_messages_legacy');

        Schema::create('outbox_messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_id');
            $table->string('aggregate_type');
            $table->unsignedBigInteger('aggregate_id');
            $table->string('type');
            $table->jsonb('payload');
            $table->timestampTz('created_at')->useCurrent();

            $table->unique('event_id', 'outbox_messages_debezium_event_id_unique');
            $table->index(['aggregate_type', 'aggregate_id'], 'outbox_messages_debezium_aggregate_index');
            $table->index(['type', 'created_at'], 'outbox_messages_debezium_type_created_index');
            $table->index('created_at', 'outbox_messages_debezium_created_at_index');
        });

        $legacyRows = DB::table('outbox_messages_legacy')
            ->orderBy('id')
            ->get([
                'id',
                'aggregate_type',
                'aggregate_id',
                'type',
                'payload',
                'created_at',
            ]);

        foreach ($legacyRows as $legacyRow) {
            DB::table('outbox_messages')->insert([
                'id' => $legacyRow->id,
                'event_id' => (string) Str::uuid(),
                'aggregate_type' => $legacyRow->aggregate_type,
                'aggregate_id' => $legacyRow->aggregate_id,
                'type' => $legacyRow->type,
                'payload' => $legacyRow->payload,
                'created_at' => $legacyRow->created_at,
            ]);
        }

        Schema::drop('outbox_messages_legacy');

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                "SELECT setval(pg_get_serial_sequence('outbox_messages', 'id'), COALESCE((SELECT MAX(id) FROM outbox_messages), 1), true)"
            );
        }
    }

    public function down(): void
    {
        Schema::rename('outbox_messages', 'outbox_messages_debezium');

        Schema::create('outbox_messages', function (Blueprint $table) {
            $table->id();
            $table->string('aggregate_type');
            $table->unsignedBigInteger('aggregate_id');
            $table->string('type');
            $table->jsonb('payload');
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('processed_at')->nullable();

            $table->index(['aggregate_type', 'aggregate_id'], 'outbox_messages_legacy_aggregate_index');
            $table->index(['processed_at', 'created_at'], 'outbox_messages_legacy_processed_index');
            $table->index('type', 'outbox_messages_legacy_type_index');
        });

        $debeziumRows = DB::table('outbox_messages_debezium')
            ->orderBy('id')
            ->get([
                'id',
                'aggregate_type',
                'aggregate_id',
                'type',
                'payload',
                'created_at',
            ]);

        foreach ($debeziumRows as $debeziumRow) {
            DB::table('outbox_messages')->insert([
                'id' => $debeziumRow->id,
                'aggregate_type' => $debeziumRow->aggregate_type,
                'aggregate_id' => $debeziumRow->aggregate_id,
                'type' => $debeziumRow->type,
                'payload' => $debeziumRow->payload,
                'created_at' => $debeziumRow->created_at,
                'processed_at' => null,
            ]);
        }

        Schema::drop('outbox_messages_debezium');

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                "SELECT setval(pg_get_serial_sequence('outbox_messages', 'id'), COALESCE((SELECT MAX(id) FROM outbox_messages), 1), true)"
            );
        }
    }
};
