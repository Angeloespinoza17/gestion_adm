<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_work_orders', function (Blueprint $table) {
            $table->string('closure_document_reference')->nullable()->after('photo_reference');
            $table->string('closure_document_original_name')->nullable()->after('closure_document_reference');
            $table->timestamp('closed_at')->nullable()->after('resolution_notes');
            $table->foreignId('closed_by_user_id')
                ->nullable()
                ->after('closed_at')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_work_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('closed_by_user_id');
            $table->dropColumn([
                'closure_document_reference',
                'closure_document_original_name',
                'closed_at',
            ]);
        });
    }
};
