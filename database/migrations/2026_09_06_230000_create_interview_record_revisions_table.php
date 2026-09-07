<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('interview_record_revisions')) {
            return;
        }

        Schema::create('interview_record_revisions', function (Blueprint $table) {
            $table->id();
            $table->string('module', 40);
            $table->string('record_type');
            $table->unsignedBigInteger('record_id');
            $table->unsignedBigInteger('case_id')->nullable();
            $table->foreignId('edited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 1000);
            $table->json('changed_fields');
            $table->longText('before_payload');
            $table->longText('after_payload');
            $table->timestamps();

            $table->index(['module', 'record_id', 'created_at'], 'interview_revisions_record_date_idx');
            $table->index(['module', 'case_id', 'created_at'], 'interview_revisions_case_date_idx');
        });
    }

    public function down(): void
    {
        // Se conserva el historial cifrado para impedir pérdida de trazabilidad.
    }
};
