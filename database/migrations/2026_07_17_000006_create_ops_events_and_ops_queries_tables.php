<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ops_events', function (Blueprint $table) {
            $table->id();
            $table->string('type', 32); // scheduler | job | api | webhook
            $table->string('name', 255);
            $table->string('status', 32); // running | success | failed | skipped
            $table->unsignedInteger('duration_ms')->nullable();
            $table->foreignId('profile_id')->nullable()->constrained('profiles')->nullOnDelete();
            $table->uuid('correlation_id');
            $table->json('meta')->nullable();
            $table->unsignedInteger('query_count')->default(0);
            $table->unsignedInteger('query_ms_total')->default(0);
            $table->timestampTz('started_at');
            $table->timestampTz('finished_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('ops_queries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ops_event_id')->constrained('ops_events')->cascadeOnDelete();
            $table->text('sql');
            $table->unsignedInteger('duration_ms');
            $table->string('connection', 64)->nullable();
            $table->timestampTz('captured_at');
            $table->timestampsTz();
        });

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('CREATE INDEX ops_events_started_idx ON ops_events (started_at DESC)');
            DB::statement('CREATE INDEX ops_events_type_started_idx ON ops_events (type, started_at DESC)');
            DB::statement('CREATE INDEX ops_events_profile_started_idx ON ops_events (profile_id, started_at DESC)');
            DB::statement('CREATE INDEX ops_queries_event_idx ON ops_queries (ops_event_id)');
        } else {
            Schema::table('ops_events', function (Blueprint $table) {
                $table->index('started_at');
                $table->index(['type', 'started_at']);
                $table->index(['profile_id', 'started_at']);
            });
            Schema::table('ops_queries', function (Blueprint $table) {
                $table->index('ops_event_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ops_queries');
        Schema::dropIfExists('ops_events');
    }
};
