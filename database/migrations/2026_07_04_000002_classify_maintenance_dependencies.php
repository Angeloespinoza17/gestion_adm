<?php

use App\Models\MaintenanceDependency;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_dependencies', function (Blueprint $table) {
            if (!Schema::hasColumn('maintenance_dependencies', 'dependency_kind')) {
                $table->string('dependency_kind', 30)
                    ->default(MaintenanceDependency::KIND_SPACE)
                    ->after('id')
                    ->index('maintenance_dependencies_kind_idx');
            }

            if (!Schema::hasColumn('maintenance_dependencies', 'is_inventory_auditable')) {
                $table->boolean('is_inventory_auditable')
                    ->default(true)
                    ->after('is_reservable')
                    ->index('maintenance_dependencies_inventory_audit_idx');
            }

            if (!Schema::hasColumn('maintenance_dependencies', 'is_maintenance_location')) {
                $table->boolean('is_maintenance_location')
                    ->default(true)
                    ->after('is_inventory_auditable')
                    ->index('maintenance_dependencies_maintenance_location_idx');
            }
        });

        DB::table('maintenance_dependencies')->update([
            'dependency_kind' => MaintenanceDependency::KIND_SPACE,
            'is_inventory_auditable' => true,
            'is_maintenance_location' => true,
        ]);

        $technicalPatterns = [
            'tablero',
            'caja de redes',
            'sistema de audio',
            'parlante',
            'canasta',
            'telon',
            'telón',
            'combustion',
            'combustión',
            'estaciones de computadores',
            'caseta gas',
            'switch',
            'rack',
            'ups',
        ];

        DB::table('maintenance_dependencies')
            ->where(function ($query) use ($technicalPatterns) {
                foreach ($technicalPatterns as $pattern) {
                    $query->orWhereRaw('LOWER(name) LIKE ?', ['%' . mb_strtolower($pattern) . '%']);
                }
            })
            ->update([
                'dependency_kind' => MaintenanceDependency::KIND_TECHNICAL_ASSET,
                'is_reservable' => false,
                'requires_approval' => false,
                'is_inventory_auditable' => false,
                'is_maintenance_location' => false,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        Schema::table('maintenance_dependencies', function (Blueprint $table) {
            if (Schema::hasColumn('maintenance_dependencies', 'is_maintenance_location')) {
                $table->dropIndex('maintenance_dependencies_maintenance_location_idx');
                $table->dropColumn('is_maintenance_location');
            }

            if (Schema::hasColumn('maintenance_dependencies', 'is_inventory_auditable')) {
                $table->dropIndex('maintenance_dependencies_inventory_audit_idx');
                $table->dropColumn('is_inventory_auditable');
            }

            if (Schema::hasColumn('maintenance_dependencies', 'dependency_kind')) {
                $table->dropIndex('maintenance_dependencies_kind_idx');
                $table->dropColumn('dependency_kind');
            }
        });
    }
};
