<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('infirmary_attentions', function (Blueprint $table) {
            $table->unsignedBigInteger('school_insurance_number')
                ->nullable()
                ->after('correlative_number');
        });

        DB::table('infirmary_attentions')
            ->where('subject_type', 'student')
            ->whereIn('attention_category', ['accidente_menor', 'accidente_mayor'])
            ->whereNotNull('correlative_number')
            ->update([
                'school_insurance_number' => DB::raw('correlative_number'),
            ]);

        Schema::table('infirmary_attentions', function (Blueprint $table) {
            $table->unique(
                'school_insurance_number',
                'inf_attn_school_insurance_number_unq',
            );
        });

        Schema::create('infirmary_sequence_adjustments', function (Blueprint $table) {
            $table->id();
            $table->string('sequence_key', 40)->index();
            $table->unsignedBigInteger('previous_last_number');
            $table->unsignedBigInteger('new_last_number');
            $table->unsignedBigInteger('next_number');
            $table->string('reason', 500);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $lastNumber = (int) DB::table('infirmary_attentions')
            ->max('school_insurance_number');

        DB::table('infirmary_attention_sequences')->updateOrInsert(
            ['subject_type' => 'school_insurance'],
            [
                'last_number' => $lastNumber,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('infirmary_sequence_adjustments');

        DB::table('infirmary_attention_sequences')
            ->where('subject_type', 'school_insurance')
            ->delete();

        Schema::table('infirmary_attentions', function (Blueprint $table) {
            $table->dropUnique('inf_attn_school_insurance_number_unq');
            $table->dropColumn('school_insurance_number');
        });
    }
};
