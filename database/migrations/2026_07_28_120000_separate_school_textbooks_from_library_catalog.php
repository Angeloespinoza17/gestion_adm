<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $isSqlite = DB::connection()->getDriverName() === 'sqlite';

        if (! Schema::hasTable('biblioteca_texto_titulos')) {
            Schema::create('biblioteca_texto_titulos', function (Blueprint $table) {
                $table->id();
                $table->string('identity_key', 64)->unique();
                $table->string('title');
                $table->string('subject', 120);
                $table->string('publisher')->nullable();
                $table->string('isbn', 32)->nullable();
                $table->foreignId('education_level_id')
                    ->nullable()
                    ->constrained('education_levels', indexName: 'bib_txt_title_level_fk')
                    ->nullOnDelete();
                $table->boolean('active')->default(true)->index();
                $table->text('notes')->nullable();
                $table->foreignId('created_by')
                    ->nullable()
                    ->constrained('users', indexName: 'bib_txt_title_created_by_fk')
                    ->nullOnDelete();
                $table->foreignId('updated_by')
                    ->nullable()
                    ->constrained('users', indexName: 'bib_txt_title_updated_by_fk')
                    ->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('biblioteca_texto_recepcion_items', 'biblioteca_texto_titulo_id')) {
            Schema::table('biblioteca_texto_recepcion_items', function (Blueprint $table) {
                $table->foreignId('biblioteca_texto_titulo_id')
                    ->nullable()
                    ->after('biblioteca_texto_recepcion_id')
                    ->constrained('biblioteca_texto_titulos', indexName: 'bib_txt_rec_item_title_fk')
                    ->restrictOnDelete();
            });
        }

        if (! Schema::hasColumn('biblioteca_texto_orden_items', 'biblioteca_texto_titulo_id')) {
            Schema::table('biblioteca_texto_orden_items', function (Blueprint $table) {
                $table->foreignId('biblioteca_texto_titulo_id')
                    ->nullable()
                    ->after('biblioteca_texto_orden_id')
                    ->constrained('biblioteca_texto_titulos', indexName: 'bib_txt_order_item_title_fk')
                    ->restrictOnDelete();
            });
        }

        $normalize = static fn (?string $value): string => mb_strtolower(
            trim((string) preg_replace('/\s+/u', ' ', (string) $value)),
            'UTF-8'
        );

        $findOrCreateTitle = static function (
            string $title,
            string $subject,
            ?string $publisher,
            ?int $educationLevelId
        ) use ($normalize): int {
            $identityKey = hash('sha256', implode('|', [
                $normalize($title),
                $normalize($subject),
                (string) ($educationLevelId ?? 0),
            ]));

            $existingId = DB::table('biblioteca_texto_titulos')
                ->where('identity_key', $identityKey)
                ->value('id');

            if ($existingId) {
                if ($publisher) {
                    DB::table('biblioteca_texto_titulos')
                        ->where('id', $existingId)
                        ->whereNull('publisher')
                        ->update(['publisher' => $publisher, 'updated_at' => now()]);
                }

                return (int) $existingId;
            }

            return (int) DB::table('biblioteca_texto_titulos')->insertGetId([
                'identity_key' => $identityKey,
                'title' => $title,
                'subject' => $subject,
                'publisher' => $publisher,
                'education_level_id' => $educationLevelId,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        };

        DB::table('biblioteca_texto_recepcion_items')
            ->whereNull('biblioteca_texto_titulo_id')
            ->orderBy('id')
            ->get()
            ->each(function ($item) use ($findOrCreateTitle) {
                $titleId = $findOrCreateTitle(
                    $item->title,
                    $item->subject,
                    $item->publisher,
                    $item->education_level_id ? (int) $item->education_level_id : null,
                );

                DB::table('biblioteca_texto_recepcion_items')
                    ->where('id', $item->id)
                    ->update(['biblioteca_texto_titulo_id' => $titleId]);
            });

        DB::table('biblioteca_texto_orden_items as item')
            ->leftJoin('biblioteca_texto_ordenes as orden', 'orden.id', '=', 'item.biblioteca_texto_orden_id')
            ->whereNull('item.biblioteca_texto_titulo_id')
            ->select([
                'item.id',
                'item.title',
                'item.subject',
                'orden.education_level_id',
            ])
            ->orderBy('item.id')
            ->get()
            ->each(function ($item) use ($findOrCreateTitle) {
                $titleId = $findOrCreateTitle(
                    $item->title,
                    $item->subject,
                    null,
                    $item->education_level_id ? (int) $item->education_level_id : null,
                );

                DB::table('biblioteca_texto_orden_items')
                    ->where('id', $item->id)
                    ->update(['biblioteca_texto_titulo_id' => $titleId]);
            });

        if (Schema::hasColumn('biblioteca_texto_recepcion_items', 'biblioteca_obra_id')) {
            if ($isSqlite || $this->foreignKeyExists('biblioteca_texto_recepcion_items', 'bib_txt_rec_item_work_fk')) {
                Schema::table('biblioteca_texto_recepcion_items', function (Blueprint $table) use ($isSqlite) {
                    $table->dropForeign($isSqlite ? ['biblioteca_obra_id'] : 'bib_txt_rec_item_work_fk');
                });
            }

            Schema::table('biblioteca_texto_recepcion_items', function (Blueprint $table) {
                $table->dropIndex('bib_text_rec_work_level_idx');
                $table->dropColumn('biblioteca_obra_id');
            });
        }

        if (! $this->indexExists('biblioteca_texto_recepcion_items', 'bib_text_rec_title_level_idx')) {
            Schema::table('biblioteca_texto_recepcion_items', function (Blueprint $table) {
                $table->index(
                    ['biblioteca_texto_titulo_id', 'education_level_id'],
                    'bib_text_rec_title_level_idx'
                );
            });
        }

        if (Schema::hasColumn('biblioteca_texto_orden_items', 'biblioteca_obra_id')) {
            if ($isSqlite || $this->foreignKeyExists('biblioteca_texto_orden_items', 'bib_txt_order_item_work_fk')) {
                Schema::table('biblioteca_texto_orden_items', function (Blueprint $table) use ($isSqlite) {
                    $table->dropForeign($isSqlite ? ['biblioteca_obra_id'] : 'bib_txt_order_item_work_fk');
                });
            }

            Schema::table('biblioteca_texto_orden_items', function (Blueprint $table) {
                $table->dropColumn('biblioteca_obra_id');
            });
        }
    }

    public function down(): void
    {
        $isSqlite = DB::connection()->getDriverName() === 'sqlite';

        if (! Schema::hasColumn('biblioteca_texto_recepcion_items', 'biblioteca_obra_id')) {
            Schema::table('biblioteca_texto_recepcion_items', function (Blueprint $table) {
                $table->foreignId('biblioteca_obra_id')
                    ->nullable()
                    ->after('biblioteca_texto_recepcion_id')
                    ->constrained('biblioteca_obras', indexName: 'bib_txt_rec_item_work_fk')
                    ->nullOnDelete();
                $table->index(['biblioteca_obra_id', 'education_level_id'], 'bib_text_rec_work_level_idx');
            });
        }

        if (Schema::hasColumn('biblioteca_texto_recepcion_items', 'biblioteca_texto_titulo_id')) {
            Schema::table('biblioteca_texto_recepcion_items', function (Blueprint $table) use ($isSqlite) {
                $table->dropForeign($isSqlite ? ['biblioteca_texto_titulo_id'] : 'bib_txt_rec_item_title_fk');
            });
            Schema::table('biblioteca_texto_recepcion_items', function (Blueprint $table) {
                $table->dropIndex('bib_text_rec_title_level_idx');
                $table->dropColumn('biblioteca_texto_titulo_id');
            });
        }

        if (! Schema::hasColumn('biblioteca_texto_orden_items', 'biblioteca_obra_id')) {
            Schema::table('biblioteca_texto_orden_items', function (Blueprint $table) {
                $table->foreignId('biblioteca_obra_id')
                    ->nullable()
                    ->after('biblioteca_texto_orden_id')
                    ->constrained('biblioteca_obras', indexName: 'bib_txt_order_item_work_fk')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasColumn('biblioteca_texto_orden_items', 'biblioteca_texto_titulo_id')) {
            Schema::table('biblioteca_texto_orden_items', function (Blueprint $table) use ($isSqlite) {
                $table->dropForeign($isSqlite ? ['biblioteca_texto_titulo_id'] : 'bib_txt_order_item_title_fk');
            });
            Schema::table('biblioteca_texto_orden_items', function (Blueprint $table) {
                $table->dropColumn('biblioteca_texto_titulo_id');
            });
        }

        Schema::dropIfExists('biblioteca_texto_titulos');
    }

    private function foreignKeyExists(string $table, string $name): bool
    {
        return collect(Schema::getForeignKeys($table))
            ->contains(fn (array $foreignKey) => $foreignKey['name'] === $name);
    }

    private function indexExists(string $table, string $name): bool
    {
        return collect(Schema::getIndexes($table))
            ->contains(fn (array $index) => $index['name'] === $name);
    }
};
