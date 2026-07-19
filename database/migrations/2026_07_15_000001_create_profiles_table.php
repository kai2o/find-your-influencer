<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profiles', function (Blueprint $table) {
            $table->id();
            $table->string('username');
            $table->string('platform')->default('instagram');
            $table->string('status')->default('pending');
            $table->text('bio')->nullable();
            $table->text('profile_picture_url')->nullable();
            $table->unsignedBigInteger('followers_count')->nullable();
            $table->unsignedBigInteger('following_count')->nullable();
            $table->unsignedBigInteger('posts_count')->nullable();
            $table->text('last_error')->nullable();
            $table->timestampTz('last_refreshed_at')->nullable();
            $table->timestampsTz();
        });

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX profiles_username_lower_unique ON profiles (LOWER(username))');
            DB::statement('CREATE INDEX profiles_status_last_refreshed_idx ON profiles (status, last_refreshed_at DESC) INCLUDE (username)');
        } else {
            Schema::table('profiles', function (Blueprint $table) {
                $table->unique('username');
                $table->index(['status', 'last_refreshed_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('profiles');
    }
};
