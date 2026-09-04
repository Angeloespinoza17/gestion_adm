<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('maintenance_evidence_photos')
            || ! Schema::hasColumn('maintenance_evidence_photos', 'maintenance_visit_checklist_response_id')) {
            return;
        }

        Schema::table('maintenance_evidence_photos', function (Blueprint $table): void {
            $table->unsignedBigInteger('maintenance_visit_checklist_response_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('maintenance_evidence_photos')
            || ! Schema::hasColumn('maintenance_evidence_photos', 'maintenance_visit_checklist_response_id')
            || DB::table('maintenance_evidence_photos')->whereNull('maintenance_visit_checklist_response_id')->exists()) {
            return;
        }

        Schema::table('maintenance_evidence_photos', function (Blueprint $table): void {
            $table->unsignedBigInteger('maintenance_visit_checklist_response_id')->nullable(false)->change();
        });
    }
};
