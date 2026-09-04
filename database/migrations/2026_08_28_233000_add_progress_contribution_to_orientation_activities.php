<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orientation_activities')) {
            return;
        }

        Schema::table('orientation_activities', function (Blueprint $table): void {
            if (! Schema::hasColumn('orientation_activities', 'contribution_percent')) {
                $table->unsignedTinyInteger('contribution_percent')
                    ->default(0)
                    ->after('status');
            }

            if (! Schema::hasColumn('orientation_activities', 'completion_percent')) {
                $table->unsignedTinyInteger('completion_percent')
                    ->default(0)
                    ->after('contribution_percent');
            }
        });
    }

    public function down(): void
    {
        // Migración aditiva: no se eliminan aportes ni porcentajes de cumplimiento registrados.
    }
};
