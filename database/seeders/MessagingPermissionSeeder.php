<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Models\Role;
use App\Models\SystemModule;
use Illuminate\Database\Seeder;

class MessagingPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $definitions = ['messaging.send_announcement' => 'Enviar anuncios institucionales', 'messaging.view_receipts' => 'Ver informes globales de acuses', 'messaging.send_reminder' => 'Enviar recordatorios de acuse', 'messaging.waive_acknowledgement' => 'Eximir acuses pendientes', 'messaging.moderate' => 'Moderar mensajería'];
        $permissions = collect($definitions)->map(fn ($name, $slug) => Permission::query()->updateOrCreate(['slug' => $slug], ['name' => $name, 'description' => $name, 'active' => true]));
        $module = SystemModule::query()->updateOrCreate(['slug' => 'messaging'], ['name' => 'Mensajería', 'frontend_route' => '/mensajeria', 'icon' => 'bx-message-rounded-dots', 'sort_order' => 43, 'active' => true, 'parent_id' => null]);
        $group = PermissionGroup::query()->updateOrCreate(['slug' => 'mensajeria'], ['system_module_id' => $module->id, 'name' => 'Mensajería', 'description' => 'Permisos administrativos de mensajería institucional.', 'sort_order' => 24, 'active' => true]);
        $group->permissions()->sync($permissions->pluck('id'));
        Role::query()->whereIn('slug', ['super_admin', 'administrador', 'direccion'])->get()->each(function ($role) use ($permissions, $module) {
            $role->permissions()->syncWithoutDetaching($permissions->pluck('id'));
            $role->modules()->syncWithoutDetaching([$module->id]);
        });
    }
}
