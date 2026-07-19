<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained('profiles')->cascadeOnDelete();
            $table->unsignedBigInteger('followers_count');
            $table->unsignedBigInteger('following_count')->nullable();
            $table->unsignedBigInteger('posts_count')->nullable();
            $table->text('bio')->nullable();
            $table->text('profile_picture_url')->nullable();
            $table->bigInteger('followers_delta')->nullable();
            $table->timestampTz('captured_at');
            $table->timestampsTz();
        });

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('CREATE INDEX profile_snapshots_profile_captured_idx ON profile_snapshots (profile_id, captured_at DESC)');
        } else {
            Schema::table('profile_snapshots', function (Blueprint $table) {
                $table->index(['profile_id', 'captured_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_snapshots');
    }
};
