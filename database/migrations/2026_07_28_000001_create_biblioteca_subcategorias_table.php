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
        Schema::create('biblioteca_subcategorias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('biblioteca_categoria_id')
                ->constrained('biblioteca_categorias')
                ->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('slug', 140);
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['biblioteca_categoria_id', 'name'],
                'bib_subcat_category_name_unique'
            );
            $table->unique(
                ['biblioteca_categoria_id', 'slug'],
                'bib_subcat_category_slug_unique'
            );
        });

        Schema::table('biblioteca_obras', function (Blueprint $table) {
            $table->foreignId('biblioteca_subcategoria_id')
                ->nullable()
                ->after('biblioteca_categoria_id')
                ->constrained('biblioteca_subcategorias')
                ->nullOnDelete();
        });

        $legacySubcategories = DB::table('biblioteca_obras')
            ->select(['biblioteca_categoria_id', 'subcategory'])
            ->whereNotNull('biblioteca_categoria_id')
            ->whereNotNull('subcategory')
            ->where('subcategory', '<>', '')
            ->orderBy('biblioteca_categoria_id')
            ->orderBy('subcategory')
            ->distinct()
            ->get();

        foreach ($legacySubcategories as $legacy) {
            $name = trim($legacy->subcategory);
            $subcategoryId = DB::table('biblioteca_subcategorias')
                ->where('biblioteca_categoria_id', $legacy->biblioteca_categoria_id)
                ->where('name', $name)
                ->value('id');

            if (! $subcategoryId) {
                $baseSlug = Str::slug($name) ?: 'subcategoria';
                $slug = $baseSlug;
                $suffix = 2;

                while (DB::table('biblioteca_subcategorias')
                    ->where('biblioteca_categoria_id', $legacy->biblioteca_categoria_id)
                    ->where('slug', $slug)
                    ->exists()) {
                    $slug = $baseSlug.'-'.$suffix;
                    $suffix++;
                }

                $subcategoryId = DB::table('biblioteca_subcategorias')->insertGetId([
                    'biblioteca_categoria_id' => $legacy->biblioteca_categoria_id,
                    'name' => $name,
                    'slug' => $slug,
                    'sort_order' => 0,
                    'active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('biblioteca_obras')
                ->where('biblioteca_categoria_id', $legacy->biblioteca_categoria_id)
                ->where('subcategory', $legacy->subcategory)
                ->update(['biblioteca_subcategoria_id' => $subcategoryId]);
        }
    }

    public function down(): void
    {
        Schema::table('biblioteca_obras', function (Blueprint $table) {
            $table->dropConstrainedForeignId('biblioteca_subcategoria_id');
        });

        Schema::dropIfExists('biblioteca_subcategorias');
    }
};
