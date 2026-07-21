<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_events', function (Blueprint $table) {
            $table->string('header_image_url', 2048)->nullable()->after('external_url');
            $table->string('hero_image_url', 2048)->nullable()->after('header_image_url');
            $table->string('hero_image_alt')->nullable()->after('hero_image_url');
            $table->json('highlights')->nullable()->after('hero_image_alt');
            $table->json('schedule_items')->nullable()->after('highlights');
            $table->string('gallery_intro')->nullable()->after('schedule_items');
            $table->json('gallery_images')->nullable()->after('gallery_intro');
            $table->boolean('registration_enabled')->default(false)->after('gallery_images');
            $table->string('registration_title')->nullable()->after('registration_enabled');
            $table->string('registration_button_label')->nullable()->after('registration_title');
            $table->string('registration_url', 2048)->nullable()->after('registration_button_label');
            $table->string('organizer_name')->nullable()->after('registration_url');
            $table->string('organizer_position')->nullable()->after('organizer_name');
            $table->text('organizer_description')->nullable()->after('organizer_position');
            $table->string('organizer_email')->nullable()->after('organizer_description');
            $table->string('organizer_phone')->nullable()->after('organizer_email');
            $table->string('organizer_image_url', 2048)->nullable()->after('organizer_phone');
            $table->string('organizer_image_alt')->nullable()->after('organizer_image_url');
        });
    }

    public function down(): void
    {
        Schema::table('site_events', function (Blueprint $table) {
            $table->dropColumn([
                'header_image_url',
                'hero_image_url',
                'hero_image_alt',
                'highlights',
                'schedule_items',
                'gallery_intro',
                'gallery_images',
                'registration_enabled',
                'registration_title',
                'registration_button_label',
                'registration_url',
                'organizer_name',
                'organizer_position',
                'organizer_description',
                'organizer_email',
                'organizer_phone',
                'organizer_image_url',
                'organizer_image_alt',
            ]);
        });
    }
};
