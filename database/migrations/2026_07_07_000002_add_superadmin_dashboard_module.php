<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('system_modules')) {
            return;
        }

        $settingsId = DB::table('system_modules')->where('slug', 'settings')->value('id');

        DB::table('system_modules')->updateOrInsert(
            ['slug' => 'settings_superadmin_dashboard'],
            [
                'name' => 'Dashboard gestión',
                'frontend_route' => '/admin/dashboard',
                'icon' => null,
                'sort_order' => 0,
                'active' => true,
                'parent_id' => $settingsId,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        if (!Schema::hasTable('system_modules')) {
            return;
        }

        DB::table('system_modules')
            ->where('slug', 'settings_superadmin_dashboard')
            ->delete();
    }
};
