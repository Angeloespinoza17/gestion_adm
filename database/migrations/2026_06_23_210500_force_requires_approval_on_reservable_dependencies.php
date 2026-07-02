<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('maintenance_dependencies')
            ->where('is_reservable', true)
            ->update([
                'requires_approval' => true,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
    }
};
