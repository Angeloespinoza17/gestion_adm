<?php

namespace Database\Seeders;

use App\Models\Infirmary\InfirmaryCatalogItem;
use App\Models\User;
use Illuminate\Database\Seeder;

class InfirmaryCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $actor = User::query()->where('email', 'ivonne.reyes@cnscgestion.local')->first()
            ?: User::query()->where('email', 'superadmin@cnscgestion.cl')->first()
            ?: User::query()->first();

        $this->seedAttentionCategories($actor);
    }

    private function seedAttentionCategories(?User $actor): void
    {
        $attentionCategories = [
            ['code' => 'accidente_menor', 'name' => 'Accidente menor (caída o golpe)'],
            ['code' => 'accidente_mayor', 'name' => 'Accidente mayor (herida, contusión o torcedura)'],
            ['code' => 'emocional', 'name' => 'Emocional'],
            ['code' => 'dolor_estomago', 'name' => 'Dolor de estómago'],
            ['code' => 'dolor_cabeza', 'name' => 'Dolor de cabeza'],
            ['code' => 'epistaxis', 'name' => 'Epistaxis'],
            ['code' => 'control_signos_vitales', 'name' => 'Control de signos vitales'],
            ['code' => 'herido_dolor_anterior', 'name' => 'Herido o dolor anterior'],
            ['code' => 'otro', 'name' => 'Otro'],
        ];

        foreach ($attentionCategories as $index => $category) {
            $catalogItem = InfirmaryCatalogItem::query()->firstOrNew([
                'group_key' => InfirmaryCatalogItem::GROUP_ATTENTION_CATEGORY,
                'code' => $category['code'],
            ]);

            if (! $catalogItem->exists) {
                $catalogItem->created_by = $actor?->id;
            }

            $catalogItem->fill([
                'name' => $category['name'],
                'description' => 'Categoría disponible en la ficha de atención de enfermería.',
                'sort_order' => $index + 1,
                'active' => true,
                'updated_by' => $actor?->id,
            ])->save();
        }
    }
}
