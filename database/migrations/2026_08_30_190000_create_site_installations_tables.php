<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('site_installations')) {
            Schema::create('site_installations', function (Blueprint $table): void {
                $table->id();
                $table->string('title');
                $table->string('slug')->unique();
                $table->string('category', 120)->nullable();
                $table->text('summary')->nullable();
                $table->longText('body')->nullable();
                $table->string('location_label')->nullable();
                $table->unsignedInteger('capacity')->nullable();
                $table->text('accessibility_notes')->nullable();
                $table->json('features')->nullable();
                $table->string('icon', 60)->nullable();
                $table->string('cover_image_path', 2048)->nullable();
                $table->string('cover_image_alt')->nullable();
                $table->string('meta_title', 70)->nullable();
                $table->string('meta_description', 170)->nullable();
                $table->string('status', 30)->default('draft');
                $table->boolean('active')->default(true);
                $table->boolean('featured')->default(false);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamp('published_at')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(
                    ['status', 'active', 'published_at'],
                    'site_installations_publication_idx',
                );
                $table->index(
                    ['active', 'featured', 'sort_order'],
                    'site_installations_order_idx',
                );
                $table->index(['category', 'active'], 'site_installations_category_idx');
            });
        }

        if (! Schema::hasTable('site_installation_images')) {
            Schema::create('site_installation_images', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('site_installation_id')
                    ->constrained('site_installations')
                    ->cascadeOnDelete();
                $table->string('image_path', 2048);
                $table->string('alt_text')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(
                    ['site_installation_id', 'sort_order'],
                    'site_installation_gallery_order_idx',
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('site_installation_images');
        Schema::dropIfExists('site_installations');
    }
};
