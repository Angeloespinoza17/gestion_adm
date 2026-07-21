<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news_posts', function (Blueprint $table) {
            if (! Schema::hasColumn('news_posts', 'views_count')) {
                $table->unsignedBigInteger('views_count')->default(0)->after('sort_order');
            }
        });
    }

    public function down(): void
    {
        Schema::table('news_posts', function (Blueprint $table) {
            if (Schema::hasColumn('news_posts', 'views_count')) {
                $table->dropColumn('views_count');
            }
        });
    }
};
