<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('porter_key_groups', function (Blueprint $table) {
            $table->id();
            $table->string('code', 80)->nullable()->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['active', 'name'], 'pkg_active_name_idx');
        });

        Schema::table('porter_keys', function (Blueprint $table) {
            $table
                ->foreignId('porter_key_group_id')
                ->nullable()
                ->after('department_id')
                ->constrained('porter_key_groups')
                ->nullOnDelete();
            $table->index(['porter_key_group_id', 'active'], 'pk_group_active_idx');
        });
    }

    public function down(): void
    {
        Schema::table('porter_keys', function (Blueprint $table) {
            $table->dropForeign(['porter_key_group_id']);
            $table->dropIndex('pk_group_active_idx');
            $table->dropColumn('porter_key_group_id');
        });

        Schema::dropIfExists('porter_key_groups');
    }
};
