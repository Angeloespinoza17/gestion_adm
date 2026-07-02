<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_organigram_relations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->foreignId('related_staff_id')->constrained('staff')->cascadeOnDelete();
            $table->string('relationship_type', 50);
            $table->string('custom_label')->nullable();
            $table->unsignedSmallInteger('priority')->default(1);
            $table->boolean('is_primary')->default(false);
            $table->boolean('active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['staff_id', 'related_staff_id', 'relationship_type'],
                'staff_org_rel_unique'
            );
            $table->index(['staff_id', 'relationship_type', 'active'], 'staff_org_rel_staff_type_active_idx');
            $table->index(['related_staff_id', 'relationship_type', 'active'], 'staff_org_rel_related_type_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_organigram_relations');
    }
};
