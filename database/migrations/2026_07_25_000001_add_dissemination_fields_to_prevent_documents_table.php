<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prevent_documents', function (Blueprint $table) {
            $table->boolean('is_disseminable')->default(false)->after('status');
            $table->string('mime_type', 120)->nullable()->after('document_name');
            $table->string('file_extension', 20)->nullable()->after('mime_type');
            $table->unsignedBigInteger('file_size')->nullable()->after('file_extension');
            $table->timestamp('disseminated_at')->nullable()->after('file_size');
            $table->foreignId('disseminated_by')
                ->nullable()
                ->after('disseminated_at')
                ->constrained('users')
                ->nullOnDelete();

            $table->index(['is_disseminable', 'status'], 'prevent_documents_dissemination_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('prevent_documents', function (Blueprint $table) {
            $table->dropIndex('prevent_documents_dissemination_status_idx');
            $table->dropConstrainedForeignId('disseminated_by');
            $table->dropColumn([
                'is_disseminable',
                'mime_type',
                'file_extension',
                'file_size',
                'disseminated_at',
            ]);
        });
    }
};
