<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('testimonials')) {
            Schema::create('testimonials', function (Blueprint $table): void {
                $table->id();
                $table->text('quote');
                $table->string('author_name', 120);
                $table->string('author_role', 160)->nullable();
                $table->string('image_path', 2048)->nullable();
                $table->string('external_image_url', 2048)->nullable();
                $table->string('image_alt')->nullable();
                $table->string('status', 30)->default('draft');
                $table->boolean('active')->default(true);
                $table->boolean('featured')->default(false);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamp('published_at')->nullable();
                $table->timestamp('consent_confirmed_at')->nullable();
                $table->foreignId('consent_confirmed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['status', 'active', 'published_at'], 'testimonials_publication_idx');
                $table->index(['active', 'featured', 'sort_order'], 'testimonials_order_idx');
            });
        }

        if (! Schema::hasTable('student_life_posts')) {
            Schema::create('student_life_posts', function (Blueprint $table): void {
                $table->id();
                $table->string('title');
                $table->string('slug')->unique();
                $table->string('category', 120)->nullable();
                $table->text('summary')->nullable();
                $table->longText('body')->nullable();
                $table->string('cover_image_path', 2048)->nullable();
                $table->string('external_cover_image_url', 2048)->nullable();
                $table->string('cover_image_alt')->nullable();
                $table->date('event_date')->nullable();
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

                $table->index(['status', 'active', 'published_at'], 'student_life_publication_idx');
                $table->index(['active', 'featured', 'sort_order'], 'student_life_order_idx');
                $table->index(['category', 'event_date'], 'student_life_category_date_idx');
            });
        }

        if (! Schema::hasTable('student_life_post_images')) {
            Schema::create('student_life_post_images', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('student_life_post_id')
                    ->constrained('student_life_posts')
                    ->cascadeOnDelete();
                $table->string('image_path', 2048);
                $table->string('alt_text')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(
                    ['student_life_post_id', 'sort_order'],
                    'student_life_gallery_order_idx',
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('student_life_post_images');
        Schema::dropIfExists('student_life_posts');
        Schema::dropIfExists('testimonials');
    }
};
