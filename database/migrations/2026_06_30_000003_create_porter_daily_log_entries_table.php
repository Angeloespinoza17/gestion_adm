<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('porter_daily_log_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('logged_on');
            $table->dateTime('logged_at');
            $table->string('shift_label', 80)->nullable();
            $table->string('category', 40)->default('novedad');
            $table->string('priority', 20)->default('media');
            $table->string('status', 30)->default('registrado');
            $table->string('title');
            $table->text('detail');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['logged_on', 'priority'], 'pdle_day_priority_idx');
            $table->index(['category', 'logged_at'], 'pdle_cat_logged_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('porter_daily_log_entries');
    }
};
