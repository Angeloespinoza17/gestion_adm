<?php

use App\Models\DependencyReservation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('maintenance_dependencies')
            ->where('is_reservable', true)
            ->update(['requires_approval' => true]);

        DB::table('dependency_reservations')
            ->where('status', DependencyReservation::STATUS_APPROVED)
            ->whereNull('approved_by')
            ->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('maintenance_dependencies')
                    ->whereColumn('maintenance_dependencies.id', 'dependency_reservations.maintenance_dependency_id')
                    ->where('maintenance_dependencies.is_reservable', true)
                    ->where('maintenance_dependencies.requires_approval', true);
            })
            ->update([
                'status' => DependencyReservation::STATUS_PENDING,
                'approved_at' => null,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('dependency_reservations')
            ->where('status', DependencyReservation::STATUS_PENDING)
            ->whereNull('approved_by')
            ->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('maintenance_dependencies')
                    ->whereColumn('maintenance_dependencies.id', 'dependency_reservations.maintenance_dependency_id')
                    ->where('maintenance_dependencies.is_reservable', true);
            })
            ->update([
                'status' => DependencyReservation::STATUS_APPROVED,
                'approved_at' => now(),
                'updated_at' => now(),
            ]);

        DB::table('maintenance_dependencies')
            ->where('is_reservable', true)
            ->update(['requires_approval' => false]);
    }
};
