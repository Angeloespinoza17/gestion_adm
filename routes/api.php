<?php

use App\Http\Controllers\APIController;
use App\Http\Controllers\CargoController;
use App\Http\Controllers\DeployController;
use App\Http\Controllers\MeController;
use App\Http\Controllers\MaintenanceAnnualPlanController;
use App\Http\Controllers\MaintenanceDependencyController;
use App\Http\Controllers\MaintenanceVisitController;
use App\Http\Controllers\MaintenanceWorkOrderController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SystemModuleController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/login', [APIController::class, 'login']);
Route::post('/register', [APIController::class, 'register']);
Route::post('/forget-password', [APIController::class, 'forget_pass']);
Route::post('/reset-password', [APIController::class, 'reset_pass']);
Route::get('/deploy/status', [DeployController::class, 'status']);
Route::post('/deploy', [DeployController::class, 'run']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me/modules', [MeController::class, 'modules']);
    Route::get('/me/permissions', [MeController::class, 'permissions']);
    Route::post('/logout', [APIController::class, 'logout']);
    Route::get('/logout', [APIController::class, 'logout']);

    // Administración (RBAC)
    Route::prefix('admin')->group(function () {
        Route::get('/users/catalogs', [UserController::class, 'catalogs'])->middleware('permission:administrar_usuarios');
        Route::get('/users', [UserController::class, 'index'])->middleware('permission:administrar_usuarios');
        Route::post('/users', [UserController::class, 'store'])->middleware('permission:administrar_usuarios');
        Route::get('/users/{user}', [UserController::class, 'show'])->middleware('permission:administrar_usuarios');
        Route::put('/users/{user}', [UserController::class, 'update'])->middleware('permission:administrar_usuarios');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->middleware('permission:administrar_usuarios');
        Route::put('/users/{user}/active', [UserController::class, 'setActive'])->middleware('permission:administrar_usuarios');
        Route::put('/users/{user}/roles', [UserController::class, 'setRoles'])->middleware('permission:administrar_usuarios');
        Route::put('/users/{user}/cargo', [UserController::class, 'setCargo'])->middleware('permission:administrar_usuarios');

        Route::get('/roles/catalogs', [RoleController::class, 'catalogs'])->middleware('permission:administrar_roles');
        Route::get('/roles', [RoleController::class, 'index'])->middleware('permission:administrar_roles');
        Route::post('/roles', [RoleController::class, 'store'])->middleware('permission:administrar_roles');
        Route::get('/roles/{role}', [RoleController::class, 'show'])->middleware('permission:administrar_roles');
        Route::put('/roles/{role}', [RoleController::class, 'update'])->middleware('permission:administrar_roles');
        Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->middleware('permission:administrar_roles');
        Route::put('/roles/{role}/permissions', [RoleController::class, 'setPermissions'])->middleware('permission:administrar_roles');
        Route::put('/roles/{role}/modules', [RoleController::class, 'setModules'])->middleware('permission:administrar_roles');

        Route::get('/permissions', [PermissionController::class, 'index'])->middleware('permission:administrar_permisos');
        Route::post('/permissions', [PermissionController::class, 'store'])->middleware('permission:administrar_permisos');

        Route::get('/modules', [SystemModuleController::class, 'index'])->middleware('permission:administrar_modulos');
        Route::post('/modules', [SystemModuleController::class, 'store'])->middleware('permission:administrar_modulos');
        Route::put('/modules/{systemModule}', [SystemModuleController::class, 'update'])->middleware('permission:administrar_modulos');
        Route::put('/modules/{systemModule}/active', [SystemModuleController::class, 'setActive'])->middleware('permission:administrar_modulos');

        Route::get('/cargos', [CargoController::class, 'index'])->middleware('permission:administrar_cargos');
        Route::post('/cargos', [CargoController::class, 'store'])->middleware('permission:administrar_cargos');
        Route::put('/cargos/{cargo}', [CargoController::class, 'update'])->middleware('permission:administrar_cargos');
        Route::put('/cargos/{cargo}/active', [CargoController::class, 'setActive'])->middleware('permission:administrar_cargos');
    });

    // Mantención: dependencias
    Route::get('/maintenance/catalogs', [MaintenanceDependencyController::class, 'catalogs'])
        ->middleware('permission:ver_mantencion');
    Route::get('/maintenance/dependencies', [MaintenanceDependencyController::class, 'index'])
        ->middleware('permission:ver_mantencion');
    Route::post('/maintenance/dependencies', [MaintenanceDependencyController::class, 'store'])
        ->middleware('permission:ver_mantencion');
    Route::get('/maintenance/dependencies/{maintenanceDependency}', [MaintenanceDependencyController::class, 'show'])
        ->middleware('permission:ver_mantencion');
    Route::put('/maintenance/dependencies/{maintenanceDependency}', [MaintenanceDependencyController::class, 'update'])
        ->middleware('permission:ver_mantencion');
    Route::delete('/maintenance/dependencies/{maintenanceDependency}', [MaintenanceDependencyController::class, 'destroy'])
        ->middleware('permission:ver_mantencion');

    // Mantención: OT
    Route::get('/maintenance/work-orders/catalogs', [MaintenanceWorkOrderController::class, 'catalogs'])
        ->middleware('permission:ver_mantencion');
    Route::get('/maintenance/work-orders/workload', [MaintenanceWorkOrderController::class, 'workload'])
        ->middleware('permission:ver_reportes_mantencion');
    Route::get('/maintenance/work-orders/assignee-report', [MaintenanceWorkOrderController::class, 'assigneeReport'])
        ->middleware('permission:exportar_mantencion');

    Route::get('/maintenance/work-orders', [MaintenanceWorkOrderController::class, 'index'])
        ->middleware('permission:ver_mantencion');
    Route::post('/maintenance/work-orders', [MaintenanceWorkOrderController::class, 'store'])
        ->middleware('permission:crear_ot');
    Route::get('/maintenance/work-orders/{maintenanceWorkOrder}', [MaintenanceWorkOrderController::class, 'show'])
        ->middleware('permission:ver_mantencion');
    Route::put('/maintenance/work-orders/{maintenanceWorkOrder}', [MaintenanceWorkOrderController::class, 'update'])
        ->middleware('permission:editar_ot');
    Route::delete('/maintenance/work-orders/{maintenanceWorkOrder}', [MaintenanceWorkOrderController::class, 'destroy'])
        ->middleware('permission:editar_ot');

    // Mantención: visitas
    Route::get('/maintenance/visits/catalogs', [MaintenanceVisitController::class, 'catalogs'])
        ->middleware('permission:ver_visitas_mantencion');
    Route::get('/maintenance/visits/{maintenanceVisit}/checklist', [MaintenanceVisitController::class, 'checklist'])
        ->middleware('permission:ver_visitas_mantencion');
    Route::post('/maintenance/visits/{maintenanceVisit}/checklist', [MaintenanceVisitController::class, 'upsertChecklist'])
        ->middleware('permission:gestionar_visitas_mantencion');
    Route::post('/maintenance/visits/{maintenanceVisit}/checklist-photo', [MaintenanceVisitController::class, 'uploadChecklistPhoto'])
        ->middleware('permission:gestionar_visitas_mantencion');
    Route::post('/maintenance/visit-checklist-responses/{checklistResponse}/create-work-order', [MaintenanceVisitController::class, 'createWorkOrderFromFinding'])
        ->middleware('permission:crear_ot');

    Route::get('/maintenance/visits', [MaintenanceVisitController::class, 'index'])
        ->middleware('permission:ver_visitas_mantencion');
    Route::post('/maintenance/visits', [MaintenanceVisitController::class, 'store'])
        ->middleware('permission:gestionar_visitas_mantencion');
    Route::get('/maintenance/visits/{maintenanceVisit}', [MaintenanceVisitController::class, 'show'])
        ->middleware('permission:ver_visitas_mantencion');
    Route::put('/maintenance/visits/{maintenanceVisit}', [MaintenanceVisitController::class, 'update'])
        ->middleware('permission:gestionar_visitas_mantencion');
    Route::delete('/maintenance/visits/{maintenanceVisit}', [MaintenanceVisitController::class, 'destroy'])
        ->middleware('permission:gestionar_visitas_mantencion');

    // Mantención: plan anual
    Route::get('/maintenance/annual-plans/catalogs', [MaintenanceAnnualPlanController::class, 'catalogs'])
        ->middleware('permission:ver_plan_anual_mantencion');
    Route::get('/maintenance/annual-plans', [MaintenanceAnnualPlanController::class, 'index'])
        ->middleware('permission:ver_plan_anual_mantencion');
    Route::post('/maintenance/annual-plans', [MaintenanceAnnualPlanController::class, 'store'])
        ->middleware('permission:gestionar_plan_anual_mantencion');
    Route::get('/maintenance/annual-plans/{maintenanceAnnualPlan}', [MaintenanceAnnualPlanController::class, 'show'])
        ->middleware('permission:ver_plan_anual_mantencion');
    Route::put('/maintenance/annual-plans/{maintenanceAnnualPlan}', [MaintenanceAnnualPlanController::class, 'update'])
        ->middleware('permission:gestionar_plan_anual_mantencion');
    Route::delete('/maintenance/annual-plans/{maintenanceAnnualPlan}', [MaintenanceAnnualPlanController::class, 'destroy'])
        ->middleware('permission:gestionar_plan_anual_mantencion');
});
