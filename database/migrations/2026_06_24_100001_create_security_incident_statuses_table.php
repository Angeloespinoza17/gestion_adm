<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_incident_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('color', 20)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_closed')->default(false);
            $table->timestamps();
        });

        DB::table('security_incident_statuses')->insert([
            [
                'code' => 'pendiente',
                'name' => 'Pendiente',
                'color' => 'warning',
                'sort_order' => 1,
                'is_closed' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'en_revision',
                'name' => 'En revisión',
                'color' => 'info',
                'sort_order' => 2,
                'is_closed' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'derivada',
                'name' => 'Derivada',
                'color' => 'primary',
                'sort_order' => 3,
                'is_closed' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'resuelta',
                'name' => 'Resuelta',
                'color' => 'success',
                'sort_order' => 4,
                'is_closed' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'descartada',
                'name' => 'Descartada',
                'color' => 'secondary',
                'sort_order' => 5,
                'is_closed' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('security_incident_statuses');
    }
};
