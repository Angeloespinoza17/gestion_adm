<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_dependencies', function (Blueprint $table) {
            if (!Schema::hasColumn('maintenance_dependencies', 'parent_dependency_id')) {
                $table->foreignId('parent_dependency_id')
                    ->nullable()
                    ->after('dependency_kind')
                    ->constrained('maintenance_dependencies')
                    ->nullOnDelete();

                $table->index('parent_dependency_id', 'maintenance_dependencies_parent_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_dependencies', function (Blueprint $table) {
            if (Schema::hasColumn('maintenance_dependencies', 'parent_dependency_id')) {
                $table->dropForeign(['parent_dependency_id']);
                $table->dropIndex('maintenance_dependencies_parent_idx');
                $table->dropColumn('parent_dependency_id');
            }
        });
    }
};
