<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('system_events');
    }

    public function down(): void
    {
        // Intentionally empty — system_events was superseded by ops_events.
    }
};
