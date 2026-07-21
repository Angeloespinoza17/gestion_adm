<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('infirmary_medication_authorizations', function (Blueprint $table) {
            if (! Schema::hasColumn('infirmary_medication_authorizations', 'dose_amount')) {
                $table->decimal('dose_amount', 10, 2)->nullable()->after('dose');
            }

            if (! Schema::hasColumn('infirmary_medication_authorizations', 'dose_unit')) {
                $table->string('dose_unit', 20)->nullable()->after('dose_amount');
            }

            if (! Schema::hasColumn('infirmary_medication_authorizations', 'administration_route')) {
                $table->string('administration_route', 40)->default('oral')->after('dose_unit');
            }

            if (! Schema::hasColumn('infirmary_medication_authorizations', 'regimen_type')) {
                $table->string('regimen_type', 40)->default('permanente')->after('schedule_text');
            }

            if (! Schema::hasColumn('infirmary_medication_authorizations', 'duration_quantity')) {
                $table->unsignedSmallInteger('duration_quantity')->nullable()->after('regimen_type');
            }
        });

        Schema::table('infirmary_medication_administrations', function (Blueprint $table) {
            if (! Schema::hasColumn('infirmary_medication_administrations', 'administration_status')) {
                $table->string('administration_status', 40)->default('administrada')->after('administered_at');
            }

            if (! Schema::hasColumn('infirmary_medication_administrations', 'dose_amount')) {
                $table->decimal('dose_amount', 10, 2)->nullable()->after('quantity_administered');
            }

            if (! Schema::hasColumn('infirmary_medication_administrations', 'dose_unit')) {
                $table->string('dose_unit', 20)->nullable()->after('dose_amount');
            }

            if (! Schema::hasColumn('infirmary_medication_administrations', 'administration_route')) {
                $table->string('administration_route', 40)->nullable()->after('dose_unit');
            }

            if (! Schema::hasColumn('infirmary_medication_administrations', 'non_administration_reason')) {
                $table->string('non_administration_reason', 191)->nullable()->after('schedule_reference');
            }
        });
    }

    public function down(): void
    {
        Schema::table('infirmary_medication_administrations', function (Blueprint $table) {
            $columns = [
                'administration_status',
                'dose_amount',
                'dose_unit',
                'administration_route',
                'non_administration_reason',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('infirmary_medication_administrations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('infirmary_medication_authorizations', function (Blueprint $table) {
            $columns = [
                'dose_amount',
                'dose_unit',
                'administration_route',
                'regimen_type',
                'duration_quantity',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('infirmary_medication_authorizations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
