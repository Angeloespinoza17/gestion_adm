<?php

namespace Database\Seeders;

use Database\Seeders\Modules\ContractsModuleSeeder;
use Database\Seeders\Modules\InventoryManagementSeeder;
use Database\Seeders\Modules\InventoryModuleSeeder;
use Database\Seeders\Modules\MaintenanceModuleSeeder;
use Database\Seeders\Modules\PorterModuleSeeder;
use Database\Seeders\Modules\RelevantCalendarModuleSeeder;
use Database\Seeders\Modules\SecurityModuleSeeder;
use Database\Seeders\Modules\ScheduleModuleSeeder;
use Database\Seeders\Modules\SpacesModuleSeeder;
use Database\Seeders\Modules\StaffModuleSeeder;
use Database\Seeders\Modules\StaffPermissionModuleSeeder;
use Database\Seeders\Modules\StudentModuleSeeder;
use Database\Seeders\Support\PreventsProductionSeeding;
use Illuminate\Database\Seeder;

class CompleteSoftwareSeeder extends Seeder
{
    use PreventsProductionSeeding;

    public function run(): void
    {
        $this->preventProductionSeeding();
        $this->call([
            RbacSeeder::class,
            InternalCommunicationsSeeder::class,
            TaskSeeder::class,
            ChileLocationSeeder::class,
            SchoolDepartmentSeeder::class,
            ContractClauseSeeder::class,
            PermissionTypeSeeder::class,
            DependencyTypeSeeder::class,
            InventoryCatalogSeeder::class,
            StudentModuleSeeder::class,
            StaffModuleSeeder::class,
            UserSeeder::class,
            SpacesModuleSeeder::class,
            InventoryModuleSeeder::class,
            InventoryManagementSeeder::class,
            MaintenanceModuleSeeder::class,
            ContractsModuleSeeder::class,
            StaffPermissionModuleSeeder::class,
            PorterModuleSeeder::class,
            SecurityModuleSeeder::class,
            RelevantCalendarModuleSeeder::class,
            ScheduleModuleSeeder::class,
            PrevencionRiesgosSeeder::class,
            BibliotecaSeeder::class,
            AccountingModuleSeeder::class,
            RemunerationSeeder::class,
            InformaticaSeeder::class,
            PermissionBackfillSeeder::class,
            PermissionGroupSeeder::class,
            AttendancePermissionSeeder::class,
            NavigationModuleBackfillSeeder::class,
            RoleModulePermissionSyncSeeder::class,
            RoleSuperAdminUserSeeder::class,
        ]);
    }
}
