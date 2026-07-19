<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ops_events', function (Blueprint $table) {
            $table->unsignedInteger('tokens_consumed')->default(0)->after('query_ms_total');
            $table->decimal('tokens_remaining', 10, 2)->nullable()->after('tokens_consumed');
        });
    }

    public function down(): void
    {
        Schema::table('ops_events', function (Blueprint $table) {
            $table->dropColumn(['tokens_consumed', 'tokens_remaining']);
        });
    }
};
