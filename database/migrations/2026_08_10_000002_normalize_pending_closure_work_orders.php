<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('maintenance_work_orders')
            ->where('status', 'Pendiente de cierre')
            ->update([
                'status' => 'Terminado',
                'closed_at' => null,
                'closed_by_user_id' => null,
            ]);
    }

    public function down(): void
    {
        DB::table('maintenance_work_orders')
            ->where('status', 'Terminado')
            ->where(function ($query) {
                $query
                    ->whereNull('resolution_notes')
                    ->orWhereRaw("TRIM(resolution_notes) = ''");
            })
            ->update(['status' => 'Pendiente de cierre']);
    }
};
