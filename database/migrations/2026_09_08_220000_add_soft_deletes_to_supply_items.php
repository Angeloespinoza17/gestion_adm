<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supply_items', function (Blueprint $table): void {
            $table->softDeletes()->after('reference_photo_path');
            $table->index(['section', 'deleted_at'], 'supply_items_section_deleted_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('supply_items', function (Blueprint $table): void {
            $table->dropIndex('supply_items_section_deleted_at_index');
            $table->dropSoftDeletes();
        });
    }
};
