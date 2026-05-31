<?php

use App\Http\Controllers\APIController;
use App\Http\Controllers\DeployController;
use App\Http\Controllers\MaintenanceAnnualPlanController;
use App\Http\Controllers\MaintenanceDependencyController;
use App\Http\Controllers\MaintenanceVisitController;
use App\Http\Controllers\MaintenanceWorkOrderController;
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
Route::get('/maintenance/catalogs', [MaintenanceDependencyController::class, 'catalogs']);
Route::apiResource('/maintenance/dependencies', MaintenanceDependencyController::class)
    ->parameters(['dependencies' => 'maintenanceDependency']);
Route::get('/maintenance/work-orders/catalogs', [MaintenanceWorkOrderController::class, 'catalogs']);
Route::get('/maintenance/work-orders/workload', [MaintenanceWorkOrderController::class, 'workload']);
Route::get('/maintenance/work-orders/assignee-report', [MaintenanceWorkOrderController::class, 'assigneeReport']);
Route::apiResource('/maintenance/work-orders', MaintenanceWorkOrderController::class)
    ->parameters(['work-orders' => 'maintenanceWorkOrder']);

Route::get('/maintenance/visits/catalogs', [MaintenanceVisitController::class, 'catalogs']);
Route::get('/maintenance/visits/{maintenanceVisit}/checklist', [MaintenanceVisitController::class, 'checklist']);
Route::post('/maintenance/visits/{maintenanceVisit}/checklist', [MaintenanceVisitController::class, 'upsertChecklist']);
Route::post('/maintenance/visits/{maintenanceVisit}/checklist-photo', [MaintenanceVisitController::class, 'uploadChecklistPhoto']);
Route::post('/maintenance/visit-checklist-responses/{checklistResponse}/create-work-order', [MaintenanceVisitController::class, 'createWorkOrderFromFinding']);
Route::apiResource('/maintenance/visits', MaintenanceVisitController::class)
    ->parameters(['visits' => 'maintenanceVisit']);

Route::get('/maintenance/annual-plans/catalogs', [MaintenanceAnnualPlanController::class, 'catalogs']);
Route::apiResource('/maintenance/annual-plans', MaintenanceAnnualPlanController::class)
    ->parameters(['annual-plans' => 'maintenanceAnnualPlan']);
