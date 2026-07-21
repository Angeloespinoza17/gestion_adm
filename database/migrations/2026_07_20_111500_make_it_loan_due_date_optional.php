<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('it_equipment_loans', function (Blueprint $table) {
            $table->dateTime('due_at')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('it_equipment_loans', function (Blueprint $table) {
            $table->dateTime('due_at')->nullable(false)->change();
        });
    }
};
