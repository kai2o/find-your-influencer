<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_events', function (Blueprint $table) {
            $table->id();
            $table->string('category', 32); // query | job | scheduler | api | rate_limit
            $table->string('name', 255);
            $table->string('status', 64)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->unsignedInteger('tokens_consumed')->default(0);
            $table->decimal('tokens_remaining', 10, 2)->nullable();
            $table->foreignId('profile_id')->nullable()->constrained('profiles')->nullOnDelete();
            $table->json('meta')->nullable();
            $table->timestampTz('occurred_at');
            $table->timestampsTz();
        });

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('CREATE INDEX system_events_category_occurred_idx ON system_events (category, occurred_at DESC)');
            DB::statement('CREATE INDEX system_events_occurred_idx ON system_events (occurred_at DESC)');
        } else {
            Schema::table('system_events', function (Blueprint $table) {
                $table->index(['category', 'occurred_at']);
                $table->index('occurred_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('system_events');
    }
};
