<?php

use Database\Seeders\LibroDigitalSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            // Este método sólo usa upserts y syncWithoutDetaching: preserva
            // permisos, módulos, roles y asignaciones existentes.
            app(LibroDigitalSeeder::class)->seedRbacAndNavigation();
        });
    }

    public function down(): void
    {
        // Migración forward-only: no se eliminan accesos ni registros RBAC.
    }
};
