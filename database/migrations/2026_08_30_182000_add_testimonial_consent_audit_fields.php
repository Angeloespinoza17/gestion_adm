<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('testimonials')) {
            return;
        }

        if (! Schema::hasColumn('testimonials', 'consent_confirmed_at')) {
            Schema::table('testimonials', function (Blueprint $table): void {
                $table->timestamp('consent_confirmed_at')
                    ->nullable()
                    ->after('published_at');
            });
        }

        if (! Schema::hasColumn('testimonials', 'consent_confirmed_by')) {
            Schema::table('testimonials', function (Blueprint $table): void {
                $table->foreignId('consent_confirmed_by')
                    ->nullable()
                    ->after('consent_confirmed_at')
                    ->constrained('users')
                    ->nullOnDelete();
            });
        }
    }

    /**
     * Clean installations already own these columns through migration 180000.
     * A destructive down() here could remove columns that this migration did
     * not create, so rollback deliberately preserves the consent audit trail.
     */
    public function down(): void
    {
        // Intentionally additive and non-destructive.
    }
};
