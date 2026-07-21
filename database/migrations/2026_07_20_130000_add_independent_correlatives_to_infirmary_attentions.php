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
            $table->unsignedBigInteger('correlative_number')->nullable()->after('subject_type');
        });

        foreach (['student', 'staff'] as $subjectType) {
            $correlative = 0;

            DB::table('infirmary_attentions')
                ->where('subject_type', $subjectType)
                ->orderBy('id')
                ->chunkById(500, function ($attentions) use (&$correlative): void {
                    foreach ($attentions as $attention) {
                        $correlative++;

                        DB::table('infirmary_attentions')
                            ->where('id', $attention->id)
                            ->update(['correlative_number' => $correlative]);
                    }
                });
        }

        Schema::table('infirmary_attentions', function (Blueprint $table) {
            $table->unique(
                ['subject_type', 'correlative_number'],
                'inf_attn_subject_correlative_unq'
            );
        });

        Schema::create('infirmary_attention_sequences', function (Blueprint $table) {
            $table->string('subject_type', 20)->primary();
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();
        });

        $now = now();

        foreach (['student', 'staff'] as $subjectType) {
            DB::table('infirmary_attention_sequences')->insert([
                'subject_type' => $subjectType,
                'last_number' => (int) DB::table('infirmary_attentions')
                    ->where('subject_type', $subjectType)
                    ->max('correlative_number'),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('infirmary_attention_sequences');

        Schema::table('infirmary_attentions', function (Blueprint $table) {
            $table->dropUnique('inf_attn_subject_correlative_unq');
            $table->dropColumn('correlative_number');
        });
    }
};
