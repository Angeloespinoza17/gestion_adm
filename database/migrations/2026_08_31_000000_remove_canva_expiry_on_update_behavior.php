<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        if (Schema::hasTable('canva_oauth_states') && Schema::hasColumn('canva_oauth_states', 'expires_at')) {
            DB::statement('ALTER TABLE `canva_oauth_states` MODIFY `expires_at` DATETIME NOT NULL');
        }

        if (Schema::hasTable('canva_connections') && Schema::hasColumn('canva_connections', 'access_token_expires_at')) {
            DB::statement('ALTER TABLE `canva_connections` MODIFY `access_token_expires_at` DATETIME NOT NULL');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        if (Schema::hasTable('canva_oauth_states') && Schema::hasColumn('canva_oauth_states', 'expires_at')) {
            DB::statement('ALTER TABLE `canva_oauth_states` MODIFY `expires_at` TIMESTAMP NOT NULL');
        }

        if (Schema::hasTable('canva_connections') && Schema::hasColumn('canva_connections', 'access_token_expires_at')) {
            DB::statement('ALTER TABLE `canva_connections` MODIFY `access_token_expires_at` TIMESTAMP NOT NULL');
        }
    }
};
