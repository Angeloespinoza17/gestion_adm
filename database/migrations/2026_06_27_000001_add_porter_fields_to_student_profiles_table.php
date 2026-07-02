<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_profiles', function (Blueprint $table) {
            $table->boolean('pickup_restriction')->default(false)->after('observations');
            $table->text('pickup_restriction_notes')->nullable()->after('pickup_restriction');
            $table->text('porter_alert_notes')->nullable()->after('pickup_restriction_notes');
            $table->json('authorized_pickup_people')->nullable()->after('porter_alert_notes');
        });
    }

    public function down(): void
    {
        Schema::table('student_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'pickup_restriction',
                'pickup_restriction_notes',
                'porter_alert_notes',
                'authorized_pickup_people',
            ]);
        });
    }
};
