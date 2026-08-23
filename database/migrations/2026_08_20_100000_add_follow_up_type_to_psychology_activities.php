<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('psychology_activities', function (Blueprint $table) {
            $table->string('follow_up_type', 60)->nullable()->after('next_action_on');
        });
    }

    public function down(): void
    {
        // Se conserva la columna para no eliminar datos históricos en un rollback.
    }
};
