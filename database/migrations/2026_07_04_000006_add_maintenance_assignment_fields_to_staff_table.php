<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->boolean('can_receive_maintenance_orders')
                ->default(false)
                ->after('active')
                ->index();
            $table->string('maintenance_role', 100)
                ->nullable()
                ->after('can_receive_maintenance_orders')
                ->index();
        });

        $maintenanceCargoIds = DB::table('cargos')
            ->whereIn('slug', ['encargado_mantencion', 'auxiliar_aseo', 'auxiliar_mantenimiento'])
            ->pluck('id', 'slug');

        if ($maintenanceCargoIds->has('encargado_mantencion')) {
            DB::table('staff')
                ->where('cargo_id', $maintenanceCargoIds->get('encargado_mantencion'))
                ->update([
                    'can_receive_maintenance_orders' => true,
                    'maintenance_role' => 'encargado_mantencion',
                ]);
        }

        if ($maintenanceCargoIds->has('auxiliar_aseo')) {
            DB::table('staff')
                ->where('cargo_id', $maintenanceCargoIds->get('auxiliar_aseo'))
                ->update([
                    'can_receive_maintenance_orders' => true,
                    'maintenance_role' => 'auxiliar_aseo',
                ]);
        }

        if ($maintenanceCargoIds->has('auxiliar_mantenimiento')) {
            DB::table('staff')
                ->where('cargo_id', $maintenanceCargoIds->get('auxiliar_mantenimiento'))
                ->update([
                    'can_receive_maintenance_orders' => true,
                    'maintenance_role' => 'auxiliar_mantenimiento',
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn(['can_receive_maintenance_orders', 'maintenance_role']);
        });
    }
};
