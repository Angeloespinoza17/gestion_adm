<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('biblioteca_categorias', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->string('slug', 140)->unique();
            $table->string('code', 20)->unique();
            $table->string('color', 20)->default('#556ee6');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('biblioteca_ubicaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('biblioteca_ubicaciones')->cascadeOnDelete();
            $table->string('type', 40)->index();
            $table->string('name', 120);
            $table->string('code', 40)->unique();
            $table->string('audience_type', 40)->default('mixta')->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('active')->default(true)->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['parent_id', 'type', 'name'], 'bib_ubic_parent_type_name_unique');
        });

        Schema::create('biblioteca_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('sequence_key', 40);
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['sequence_key', 'year'], 'bib_sequence_key_year_unique');
        });

        Schema::table('biblioteca_obras', function (Blueprint $table) {
            $table->foreignId('biblioteca_categoria_id')->nullable()->constrained('biblioteca_categorias')->nullOnDelete();
            $table->foreignId('biblioteca_ubicacion_id')->nullable()->constrained('biblioteca_ubicaciones')->nullOnDelete();
            $table->string('open_library_work_key', 80)->nullable()->index();
            $table->string('open_library_edition_key', 80)->nullable()->index();
            $table->unsignedBigInteger('open_library_cover_id')->nullable();
            $table->json('source_metadata')->nullable();
        });

        Schema::table('biblioteca_ejemplares', function (Blueprint $table) {
            $table->foreignId('biblioteca_ubicacion_id')->nullable()->constrained('biblioteca_ubicaciones')->nullOnDelete();
        });

        Schema::table('biblioteca_prestamos', function (Blueprint $table) {
            $table->string('borrower_rut_snapshot', 30)->nullable()->index();
            $table->string('borrower_estate', 40)->nullable()->index();
            $table->string('pickup_person_type', 40)->nullable()->index();
            $table->string('pickup_person_name')->nullable();
            $table->string('pickup_person_rut', 30)->nullable();
            $table->string('pickup_person_email')->nullable();
            $table->string('pickup_person_relationship', 80)->nullable();
            $table->longText('signature_data')->nullable();
            $table->string('signature_name')->nullable();
            $table->string('signature_rut', 30)->nullable();
            $table->dateTime('signed_at')->nullable();
            $table->text('delivery_notes')->nullable();
        });

        Schema::table('biblioteca_uso_espacios', function (Blueprint $table) {
            $table->string('requester_type', 30)->default('interno')->index();
            $table->string('external_name')->nullable();
            $table->string('external_email')->nullable();
            $table->string('external_institution')->nullable();
            $table->string('external_phone', 40)->nullable();
        });

        Schema::create('biblioteca_texto_recepciones', function (Blueprint $table) {
            $table->id();
            $table->string('reception_code', 80)->unique();
            $table->date('received_at')->index();
            $table->string('source_name')->nullable();
            $table->string('document_reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('received_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('biblioteca_texto_recepcion_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('biblioteca_texto_recepcion_id')
                ->constrained('biblioteca_texto_recepciones', indexName: 'bib_txt_rec_item_reception_fk')
                ->cascadeOnDelete();
            $table->foreignId('biblioteca_obra_id')
                ->nullable()
                ->constrained('biblioteca_obras', indexName: 'bib_txt_rec_item_work_fk')
                ->nullOnDelete();
            $table->foreignId('education_level_id')
                ->nullable()
                ->constrained('education_levels', indexName: 'bib_txt_rec_item_level_fk')
                ->nullOnDelete();
            $table->string('title');
            $table->string('subject', 120);
            $table->string('publisher')->nullable();
            $table->unsignedInteger('quantity_received');
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['biblioteca_obra_id', 'education_level_id'], 'bib_text_rec_work_level_idx');
        });

        Schema::create('biblioteca_texto_ordenes', function (Blueprint $table) {
            $table->id();
            $table->string('order_code', 80)->unique();
            $table->foreignId('academic_year_id')
                ->constrained('academic_years', indexName: 'bib_txt_order_year_fk')
                ->cascadeOnDelete();
            $table->foreignId('education_level_id')
                ->nullable()
                ->constrained('education_levels', indexName: 'bib_txt_order_level_fk')
                ->nullOnDelete();
            $table->foreignId('course_section_id')
                ->nullable()
                ->constrained('course_sections', indexName: 'bib_txt_order_course_fk')
                ->nullOnDelete();
            $table->string('status', 40)->default('borrador')->index();
            $table->date('prepared_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('prepared_by_user_id')
                ->nullable()
                ->constrained('users', indexName: 'bib_txt_order_prepared_by_fk')
                ->nullOnDelete();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users', indexName: 'bib_txt_order_created_by_fk')
                ->nullOnDelete();
            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users', indexName: 'bib_txt_order_updated_by_fk')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['academic_year_id', 'course_section_id'], 'bib_text_order_year_course_idx');
        });

        Schema::create('biblioteca_texto_orden_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('biblioteca_texto_orden_id')
                ->constrained('biblioteca_texto_ordenes', indexName: 'bib_txt_order_item_order_fk')
                ->cascadeOnDelete();
            $table->foreignId('biblioteca_obra_id')
                ->nullable()
                ->constrained('biblioteca_obras', indexName: 'bib_txt_order_item_work_fk')
                ->nullOnDelete();
            $table->string('title');
            $table->string('subject', 120);
            $table->unsignedInteger('quantity_required')->default(0);
            $table->unsignedInteger('quantity_available')->default(0);
            $table->unsignedInteger('quantity_assigned')->default(0);
            $table->unsignedInteger('shortage_quantity')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('biblioteca_texto_entregas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('biblioteca_texto_orden_id')
                ->constrained('biblioteca_texto_ordenes', indexName: 'bib_txt_delivery_order_fk')
                ->cascadeOnDelete();
            $table->foreignId('student_profile_id')
                ->constrained('student_profiles', indexName: 'bib_txt_delivery_student_fk')
                ->cascadeOnDelete();
            $table->string('student_name_snapshot');
            $table->string('student_rut_snapshot', 30)->nullable();
            $table->string('status', 40)->default('pendiente')->index();
            $table->dateTime('delivered_at')->nullable();
            $table->longText('signature_data')->nullable();
            $table->string('signature_name')->nullable();
            $table->string('signature_rut', 30)->nullable();
            $table->dateTime('signed_at')->nullable();
            $table->text('pending_reason')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('delivered_by_user_id')
                ->nullable()
                ->constrained('users', indexName: 'bib_txt_delivery_delivered_by_fk')
                ->nullOnDelete();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users', indexName: 'bib_txt_delivery_created_by_fk')
                ->nullOnDelete();
            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users', indexName: 'bib_txt_delivery_updated_by_fk')
                ->nullOnDelete();
            $table->timestamps();

            $table->unique(['biblioteca_texto_orden_id', 'student_profile_id'], 'bib_text_delivery_order_student_unique');
        });

        Schema::create('biblioteca_texto_entrega_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('biblioteca_texto_entrega_id')
                ->constrained('biblioteca_texto_entregas', indexName: 'bib_txt_delivery_item_delivery_fk')
                ->cascadeOnDelete();
            $table->foreignId('biblioteca_texto_orden_item_id')
                ->constrained('biblioteca_texto_orden_items', indexName: 'bib_txt_delivery_item_order_item_fk')
                ->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->string('status', 40)->default('pendiente')->index();
            $table->dateTime('delivered_at')->nullable();
            $table->text('pending_reason')->nullable();
            $table->timestamps();

            $table->unique(['biblioteca_texto_entrega_id', 'biblioteca_texto_orden_item_id'], 'bib_text_delivery_item_unique');
        });

        Schema::create('biblioteca_pases', function (Blueprint $table) {
            $table->id();
            $table->string('pass_code', 80)->unique();
            $table->foreignId('student_profile_id')->constrained('student_profiles')->cascadeOnDelete();
            $table->foreignId('course_section_id')->nullable()->constrained('course_sections')->nullOnDelete();
            $table->foreignId('professor_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->string('student_name_snapshot');
            $table->string('student_rut_snapshot', 30)->nullable();
            $table->string('professor_name_snapshot')->nullable();
            $table->dateTime('issued_at')->index();
            $table->dateTime('valid_from')->index();
            $table->dateTime('valid_until')->index();
            $table->string('status', 40)->default('emitido')->index();
            $table->string('regulation_version', 80)->default('vigente');
            $table->text('reason');
            $table->longText('signature_data')->nullable();
            $table->string('signature_name')->nullable();
            $table->string('signature_rut', 30)->nullable();
            $table->dateTime('signed_at')->nullable();
            $table->dateTime('used_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('issued_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('used_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $this->seedInitialLocations();
        $this->migrateExistingCategories();
    }

    public function down(): void
    {
        Schema::dropIfExists('biblioteca_pases');
        Schema::dropIfExists('biblioteca_texto_entrega_items');
        Schema::dropIfExists('biblioteca_texto_entregas');
        Schema::dropIfExists('biblioteca_texto_orden_items');
        Schema::dropIfExists('biblioteca_texto_ordenes');
        Schema::dropIfExists('biblioteca_texto_recepcion_items');
        Schema::dropIfExists('biblioteca_texto_recepciones');

        Schema::table('biblioteca_uso_espacios', function (Blueprint $table) {
            $table->dropColumn([
                'requester_type',
                'external_name',
                'external_email',
                'external_institution',
                'external_phone',
            ]);
        });

        Schema::table('biblioteca_prestamos', function (Blueprint $table) {
            $table->dropColumn([
                'borrower_rut_snapshot',
                'borrower_estate',
                'pickup_person_type',
                'pickup_person_name',
                'pickup_person_rut',
                'pickup_person_email',
                'pickup_person_relationship',
                'signature_data',
                'signature_name',
                'signature_rut',
                'signed_at',
                'delivery_notes',
            ]);
        });

        Schema::table('biblioteca_ejemplares', function (Blueprint $table) {
            $table->dropConstrainedForeignId('biblioteca_ubicacion_id');
        });

        Schema::table('biblioteca_obras', function (Blueprint $table) {
            $table->dropConstrainedForeignId('biblioteca_categoria_id');
            $table->dropConstrainedForeignId('biblioteca_ubicacion_id');
            $table->dropColumn([
                'open_library_work_key',
                'open_library_edition_key',
                'open_library_cover_id',
                'source_metadata',
            ]);
        });

        Schema::dropIfExists('biblioteca_sequences');
        Schema::dropIfExists('biblioteca_ubicaciones');
        Schema::dropIfExists('biblioteca_categorias');
    }

    private function seedInitialLocations(): void
    {
        $now = now();

        DB::table('biblioteca_ubicaciones')->insert([
            [
                'parent_id' => null,
                'type' => 'sala',
                'name' => 'Sala 1 · Enseñanza Media',
                'code' => 'SALA-1',
                'audience_type' => 'media',
                'sort_order' => 1,
                'active' => true,
                'notes' => 'Lado de Enseñanza Media de la biblioteca.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'parent_id' => null,
                'type' => 'sala',
                'name' => 'Sala 2 · Enseñanza Básica',
                'code' => 'SALA-2',
                'audience_type' => 'basica',
                'sort_order' => 2,
                'active' => true,
                'notes' => 'Lado de Enseñanza Básica de la biblioteca.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    private function migrateExistingCategories(): void
    {
        $names = DB::table('biblioteca_obras')
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        foreach ($names as $index => $name) {
            $slug = Str::slug((string) $name);
            $code = strtoupper(Str::substr(preg_replace('/[^A-Za-z0-9]/', '', Str::ascii((string) $name)) ?: 'CAT', 0, 8));

            if (DB::table('biblioteca_categorias')->where('code', $code)->exists()) {
                $code .= '-'.($index + 1);
            }

            $categoryId = DB::table('biblioteca_categorias')->insertGetId([
                'name' => $name,
                'slug' => $slug ?: 'categoria-'.($index + 1),
                'code' => $code,
                'sort_order' => $index + 1,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('biblioteca_obras')
                ->where('category', $name)
                ->update(['biblioteca_categoria_id' => $categoryId]);
        }
    }
};
