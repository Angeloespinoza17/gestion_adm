<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('remuneration_book_alert_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->string('severity', 40)->default('requiere_revision')->index();
            $table->string('metric', 80)->index();
            $table->string('operator', 20);
            $table->decimal('threshold_value', 16, 4);
            $table->string('concept_key', 40)->nullable()->index();
            $table->string('concept_label')->nullable();
            $table->string('employee_type', 120)->nullable()->index();
            $table->boolean('enabled')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['enabled', 'metric'], 'rem_book_alert_rules_enabled_metric_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('remuneration_book_alert_rules');
    }
};
