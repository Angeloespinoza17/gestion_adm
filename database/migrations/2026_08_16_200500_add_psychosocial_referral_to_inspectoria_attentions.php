<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inspectoria_attentions', function (Blueprint $table) {
            $table->foreignId('psychosocial_referral_user_id')
                ->nullable()
                ->after('requires_follow_up')
                ->constrained('users')
                ->nullOnDelete();
            $table->string('psychosocial_referral_name_snapshot')->nullable()->after('psychosocial_referral_user_id');
            $table->string('psychosocial_referral_role_snapshot', 80)->nullable()->after('psychosocial_referral_name_snapshot');
            $table->dateTime('psychosocial_referred_at')->nullable()->after('psychosocial_referral_role_snapshot');
            $table->index(['psychosocial_referral_user_id', 'psychosocial_referred_at'], 'insp_attention_psychosocial_referral_idx');
        });
    }

    public function down(): void
    {
        Schema::table('inspectoria_attentions', function (Blueprint $table) {
            $table->dropIndex('insp_attention_psychosocial_referral_idx');
            $table->dropConstrainedForeignId('psychosocial_referral_user_id');
            $table->dropColumn([
                'psychosocial_referral_name_snapshot',
                'psychosocial_referral_role_snapshot',
                'psychosocial_referred_at',
            ]);
        });
    }
};
