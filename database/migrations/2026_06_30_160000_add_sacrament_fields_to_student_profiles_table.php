<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_profiles', function (Blueprint $table) {
            $table->date('baptism_date')->nullable()->after('physical_restrictions_details');
            $table->string('baptism_place')->nullable()->after('baptism_date');
            $table->date('first_communion_date')->nullable()->after('baptism_place');
            $table->string('first_communion_place')->nullable()->after('first_communion_date');
            $table->date('confirmation_date')->nullable()->after('first_communion_place');
            $table->string('confirmation_place')->nullable()->after('confirmation_date');
        });
    }

    public function down(): void
    {
        Schema::table('student_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'baptism_date',
                'baptism_place',
                'first_communion_date',
                'first_communion_place',
                'confirmation_date',
                'confirmation_place',
            ]);
        });
    }
};
