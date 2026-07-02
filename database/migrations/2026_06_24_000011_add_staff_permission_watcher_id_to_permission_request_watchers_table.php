<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('permission_request_watchers', function (Blueprint $table) {
            $table->foreignId('staff_permission_watcher_id')
                ->nullable()
                ->after('permission_type_watcher_id')
                ->constrained('staff_permission_watchers')
                ->nullOnDelete();

            $table->index(['staff_permission_watcher_id'], 'prw_staff_cfg_idx');
        });
    }

    public function down(): void
    {
        Schema::table('permission_request_watchers', function (Blueprint $table) {
            $table->dropIndex('prw_staff_cfg_idx');
            $table->dropConstrainedForeignId('staff_permission_watcher_id');
        });
    }
};
