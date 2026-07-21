<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('remuneration_book_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id')->nullable()->constrained('remuneration_periods')->nullOnDelete();
            $table->string('original_filename');
            $table->string('file_hash', 64)->index();
            $table->string('status', 40)->default('preview')->index();
            $table->date('book_period')->nullable()->index();
            $table->unsignedSmallInteger('year')->nullable()->index();
            $table->unsignedTinyInteger('month')->nullable()->index();
            $table->unsignedInteger('row_count')->default(0);
            $table->unsignedInteger('matched_count')->default(0);
            $table->unsignedInteger('unmatched_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->bigInteger('gross_total')->default(0);
            $table->bigInteger('net_total')->default(0);
            $table->bigInteger('total_deductions')->default(0);
            $table->bigInteger('employer_contributions')->default(0);
            $table->json('summary')->nullable();
            $table->json('errors')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['year', 'month', 'status'], 'rem_book_imports_period_status_idx');
        });

        Schema::create('remuneration_book_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_import_id')->constrained('remuneration_book_imports')->cascadeOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->unsignedInteger('row_number')->index();
            $table->string('rut', 30)->index();
            $table->string('employee_name');
            $table->string('employee_type', 120)->nullable()->index();
            $table->decimal('worked_days', 8, 2)->default(0);
            $table->decimal('weekly_hours', 8, 2)->default(0);
            $table->bigInteger('gross_taxable_amount')->default(0);
            $table->bigInteger('gross_non_taxable_amount')->default(0);
            $table->bigInteger('gross_total')->default(0);
            $table->bigInteger('taxable_amount')->default(0);
            $table->bigInteger('legal_deductions')->default(0);
            $table->bigInteger('other_deductions')->default(0);
            $table->bigInteger('total_deductions')->default(0);
            $table->bigInteger('employer_contributions')->default(0);
            $table->bigInteger('net_amount')->default(0);
            $table->json('raw_totals')->nullable();
            $table->json('raw_earnings_columns')->nullable();
            $table->json('raw_deductions_columns')->nullable();
            $table->json('raw_earnings')->nullable();
            $table->json('raw_deductions')->nullable();
            $table->json('raw_employer_contributions')->nullable();
            $table->json('errors')->nullable();
            $table->timestamps();

            $table->unique(['book_import_id', 'rut'], 'rem_book_import_rows_import_rut_unique');
            $table->index(['book_import_id', 'staff_id'], 'rem_book_import_rows_staff_idx');
        });

        Schema::table('remuneration_payrolls', function (Blueprint $table) {
            $table->string('source', 40)->default('calculated')->index()->after('calculation_version');
            $table->foreignId('book_import_id')->nullable()->after('source')->constrained('remuneration_book_imports')->nullOnDelete();
            $table->unsignedInteger('source_row_number')->nullable()->after('book_import_id');
        });
    }

    public function down(): void
    {
        Schema::table('remuneration_payrolls', function (Blueprint $table) {
            $table->dropConstrainedForeignId('book_import_id');
            $table->dropColumn(['source', 'source_row_number']);
        });

        Schema::dropIfExists('remuneration_book_import_rows');
        Schema::dropIfExists('remuneration_book_imports');
    }
};
