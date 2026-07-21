<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news_posts', function (Blueprint $table) {
            $table->string('header_image_url', 2048)->nullable()->after('image_alt');
            $table->string('author_role')->nullable()->after('author_name');
            $table->string('author_image_url', 2048)->nullable()->after('header_image_url');
            $table->string('author_image_alt')->nullable()->after('author_image_url');
            $table->unsignedSmallInteger('reading_minutes')->nullable()->after('author_image_alt');
            $table->string('comments_label', 80)->nullable()->after('reading_minutes');
            $table->json('detail_categories')->nullable()->after('comments_label');
            $table->json('toc_items')->nullable()->after('detail_categories');
            $table->text('quote_text')->nullable()->after('toc_items');
            $table->string('quote_author')->nullable()->after('quote_text');
            $table->string('secondary_section_title')->nullable()->after('quote_author');
            $table->string('secondary_image_url', 2048)->nullable()->after('secondary_section_title');
            $table->string('secondary_image_alt')->nullable()->after('secondary_image_url');
            $table->string('secondary_image_caption')->nullable()->after('secondary_image_alt');
            $table->string('secondary_image_position', 20)->default('right')->after('secondary_image_caption');
            $table->json('feature_points')->nullable()->after('secondary_image_position');
            $table->json('comparison_cards')->nullable()->after('feature_points');
            $table->json('key_principles')->nullable()->after('comparison_cards');
            $table->string('info_box_icon')->nullable()->after('key_principles');
            $table->string('info_box_title')->nullable()->after('info_box_icon');
            $table->text('info_box_text')->nullable()->after('info_box_title');
            $table->json('future_trends')->nullable()->after('info_box_text');
            $table->json('tags')->nullable()->after('future_trends');
            $table->boolean('share_enabled')->default(true)->after('tags');
        });
    }

    public function down(): void
    {
        Schema::table('news_posts', function (Blueprint $table) {
            $table->dropColumn([
                'header_image_url',
                'author_role',
                'author_image_url',
                'author_image_alt',
                'reading_minutes',
                'comments_label',
                'detail_categories',
                'toc_items',
                'quote_text',
                'quote_author',
                'secondary_section_title',
                'secondary_image_url',
                'secondary_image_alt',
                'secondary_image_caption',
                'secondary_image_position',
                'feature_points',
                'comparison_cards',
                'key_principles',
                'info_box_icon',
                'info_box_title',
                'info_box_text',
                'future_trends',
                'tags',
                'share_enabled',
            ]);
        });
    }
};
