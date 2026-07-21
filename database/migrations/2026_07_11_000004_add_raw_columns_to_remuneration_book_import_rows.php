<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('remuneration_book_import_rows', function (Blueprint $table) {
            if (!Schema::hasColumn('remuneration_book_import_rows', 'raw_earnings_columns')) {
                $table->json('raw_earnings_columns')->nullable()->after('raw_totals');
            }

            if (!Schema::hasColumn('remuneration_book_import_rows', 'raw_deductions_columns')) {
                $table->json('raw_deductions_columns')->nullable()->after('raw_earnings_columns');
            }
        });
    }

    public function down(): void
    {
        Schema::table('remuneration_book_import_rows', function (Blueprint $table) {
            if (Schema::hasColumn('remuneration_book_import_rows', 'raw_deductions_columns')) {
                $table->dropColumn('raw_deductions_columns');
            }

            if (Schema::hasColumn('remuneration_book_import_rows', 'raw_earnings_columns')) {
                $table->dropColumn('raw_earnings_columns');
            }
        });
    }
};
