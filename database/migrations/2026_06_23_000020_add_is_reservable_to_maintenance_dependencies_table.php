<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_dependencies', function (Blueprint $table) {
            $table->boolean('is_reservable')->default(false)->after('requires_approval');
            $table->index('is_reservable');
        });

        DB::table('maintenance_dependencies')
            ->whereNotNull('dependency_type_id')
            ->update(['is_reservable' => true]);
    }

    public function down(): void
    {
        Schema::table('maintenance_dependencies', function (Blueprint $table) {
            $table->dropIndex(['is_reservable']);
            $table->dropColumn('is_reservable');
        });
    }
};
