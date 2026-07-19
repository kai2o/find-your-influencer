<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE profiles ALTER COLUMN profile_picture_url TYPE TEXT');
            DB::statement('ALTER TABLE profile_snapshots ALTER COLUMN profile_picture_url TYPE TEXT');
        }

        // SQLite affinity is flexible; create migrations already use text for new installs.
    }

    public function down(): void
    {
        // Leaving as text is safe; reverting could truncate live Instagram CDN URLs.
    }
};
