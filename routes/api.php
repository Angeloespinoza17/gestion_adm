<?php

use App\Http\Controllers\APIController;
use App\Http\Controllers\CargoController;
use App\Http\Controllers\Accounting\AccountingModuleController;
use App\Http\Controllers\DeployController;
use App\Http\Controllers\MeController;
use App\Http\Controllers\MaintenanceAnnualPlanController;
use App\Http\Controllers\MaintenanceDependencyController;
use App\Http\Controllers\MaintenanceVisitController;
use App\Http\Controllers\MaintenanceWorkOrderController;
use App\Http\Controllers\OrganigramController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\Porter\PorterCatalogController;
use App\Http\Controllers\Porter\PorterDashboardController;
use App\Http\Controllers\Porter\PorterDailyLogEntryController;
use App\Http\Controllers\Porter\PorterExternalServiceEntryController;
use App\Http\Controllers\Porter\PorterGoodsMovementController;
use App\Http\Controllers\Porter\PorterKeyController;
use App\Http\Controllers\Porter\PorterReceivedItemController;
use App\Http\Controllers\Porter\PorterReportController;
use App\Http\Controllers\Porter\PorterStudentController;
use App\Http\Controllers\Porter\PorterStudentWithdrawalController;
use App\Http\Controllers\Porter\PorterVisitController;
use App\Http\Controllers\RelevantCalendar\CalendarEventAttachmentController;
use App\Http\Controllers\RelevantCalendar\CalendarEventController;
use App\Http\Controllers\RelevantCalendar\CalendarInstitutionController;
use App\Http\Controllers\RelevantCalendar\CalendarProcessTypeController;
use App\Http\Controllers\Remuneration\RemunerationModuleController;
use App\Http\Controllers\Infirmary\InfirmaryAccidentController;
use App\Http\Controllers\Infirmary\InfirmaryAttentionController;
use App\Http\Controllers\Infirmary\InfirmaryCallLogController;
use App\Http\Controllers\Infirmary\InfirmaryCatalogController;
use App\Http\Controllers\Infirmary\InfirmaryDashboardController;
use App\Http\Controllers\Infirmary\InfirmaryDocumentController;
use App\Http\Controllers\Infirmary\InfirmaryMedicationAuthorizationController;
use App\Http\Controllers\Infirmary\InfirmaryMedicationInventoryController;
use App\Http\Controllers\Infirmary\InfirmaryReportController;
use App\Http\Controllers\Infirmary\InfirmaryStudentHistoryController;
use App\Http\Controllers\ApoyoProfesional\ApoyoProfesionalAttentionController;
use App\Http\Controllers\ApoyoProfesional\ApoyoProfesionalCatalogController;
use App\Http\Controllers\ApoyoProfesional\ApoyoProfesionalDashboardController;
use App\Http\Controllers\ApoyoProfesional\ApoyoProfesionalDerivationController;
use App\Http\Controllers\ApoyoProfesional\ApoyoProfesionalDocumentController;
use App\Http\Controllers\ApoyoProfesional\ApoyoProfesionalFollowUpController;
use App\Http\Controllers\ApoyoProfesional\ApoyoProfesionalGlobalSearchController;
use App\Http\Controllers\ApoyoProfesional\ApoyoProfesionalInterviewController;
use App\Http\Controllers\ApoyoProfesional\ApoyoProfesionalPlanController;
use App\Http\Controllers\ApoyoProfesional\ApoyoProfesionalReportController;
use App\Http\Controllers\ApoyoProfesional\ApoyoProfesionalStudentHistoryController;
use App\Http\Controllers\Convivencia\ConvivenciaAttachmentController;
use App\Http\Controllers\Convivencia\ConvivenciaCaseController;
use App\Http\Controllers\Convivencia\ConvivenciaCatalogController;
use App\Http\Controllers\Convivencia\ConvivenciaComplaintController;
use App\Http\Controllers\Convivencia\ConvivenciaDailyLogController;
use App\Http\Controllers\Convivencia\ConvivenciaDashboardController;
use App\Http\Controllers\Convivencia\ConvivenciaDerivationController;
use App\Http\Controllers\Convivencia\ConvivenciaIdpsController;
use App\Http\Controllers\Convivencia\ConvivenciaInterviewController;
use App\Http\Controllers\Convivencia\ConvivenciaMeasureController;
use App\Http\Controllers\Convivencia\ConvivenciaPlanController;
use App\Http\Controllers\Convivencia\ConvivenciaProtocolController;
use App\Http\Controllers\Convivencia\ConvivenciaPublicComplaintController;
use App\Http\Controllers\Convivencia\ConvivenciaReportController;
use App\Http\Controllers\Convivencia\ConvivenciaSociogramController;
use App\Http\Controllers\RiskPrevention\RiskPreventionAccidentController;
use App\Http\Controllers\RiskPrevention\RiskPreventionCatalogController;
use App\Http\Controllers\RiskPrevention\RiskPreventionDashboardController;
use App\Http\Controllers\RiskPrevention\RiskPreventionDocumentController;
use App\Http\Controllers\RiskPrevention\RiskPreventionEmergencyController;
use App\Http\Controllers\RiskPrevention\RiskPreventionEppController;
use App\Http\Controllers\RiskPrevention\RiskPreventionFireExtinguisherController;
use App\Http\Controllers\RiskPrevention\RiskPreventionReportController;
use App\Http\Controllers\RiskPrevention\RiskPreventionTrainingController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SystemModuleController;
use App\Http\Controllers\Tasks\TaskAssignerController;
use App\Http\Controllers\Tasks\TaskController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\DebugAuthController;
use App\Http\Controllers\Inventory\InventoryCategoryController;
use App\Http\Controllers\Inventory\InventorySubcategoryController;
use App\Http\Controllers\Inventory\SupplierController as InventorySupplierController;
use App\Http\Controllers\Inventory\InventoryItemController;
use App\Http\Controllers\Inventory\InventoryItemPhotoController;
use App\Http\Controllers\Inventory\InventoryItemDocumentController;
use App\Http\Controllers\Inventory\InventoryMovementController;
use App\Http\Controllers\Inventory\InventoryStockController;
use App\Http\Controllers\Inventory\InventoryReportController;
use App\Http\Controllers\Library\BibliotecaCatalogController;
use App\Http\Controllers\Library\BibliotecaCatalogsController;
use App\Http\Controllers\Library\BibliotecaDashboardController;
use App\Http\Controllers\Library\BibliotecaGlobalSearchController;
use App\Http\Controllers\Library\BibliotecaInventoryController;
use App\Http\Controllers\Library\BibliotecaLoanController;
use App\Http\Controllers\Library\BibliotecaPlanLectorController;
use App\Http\Controllers\Library\BibliotecaReportController;
use App\Http\Controllers\Library\BibliotecaReservationController;
use App\Http\Controllers\Library\BibliotecaSpaceController;
use App\Http\Controllers\Informatica\InformaticaCatalogController;
use App\Http\Controllers\Informatica\InformaticaDashboardController;
use App\Http\Controllers\Informatica\InformaticaReportController;
use App\Http\Controllers\Informatica\ItEquipmentAttachmentController;
use App\Http\Controllers\Informatica\ItEquipmentController;
use App\Http\Controllers\Informatica\ItEquipmentLoanController;
use App\Http\Controllers\Informatica\ItEquipmentMaintenanceController;
use App\Http\Controllers\Staff\DepartmentController;
use App\Http\Controllers\Staff\Permissions\PermissionDashboardController;
use App\Http\Controllers\Staff\Permissions\PermissionReportController;
use App\Http\Controllers\Staff\Permissions\PermissionRequestController;
use App\Http\Controllers\Staff\Permissions\PermissionRequestDocumentController;
use App\Http\Controllers\Staff\Permissions\PermissionRequestReplacementController;
use App\Http\Controllers\Staff\Permissions\PermissionTypeController;
use App\Http\Controllers\Staff\Permissions\PermissionTypeWatcherController;
use App\Http\Controllers\Staff\StaffPermissionWatcherController;
use App\Http\Controllers\Staff\StaffController;
use App\Http\Controllers\Staff\StaffDocumentController;
use App\Http\Controllers\Students\AcademicYearController;
use App\Http\Controllers\Students\CourseSectionController;
use App\Http\Controllers\Students\EducationLevelController;
use App\Http\Controllers\Students\StudentController;
use App\Http\Controllers\Students\StudentEnrollmentController;
use App\Http\Controllers\Students\StudentEnrollmentManagementController;
use App\Http\Controllers\Students\StudentPromotionController;
use App\Http\Controllers\Spaces\DependencyReservationController;
use App\Http\Controllers\Spaces\DependencyTypeController;
use App\Http\Controllers\Spaces\SpaceStatisticsController;
use App\Http\Controllers\Security\SecurityCatalogController;
use App\Http\Controllers\Security\SecurityDashboardController;
use App\Http\Controllers\Security\SecurityIncidentController;
use App\Http\Controllers\Security\SecurityNotificationController;
use App\Http\Controllers\Security\SecurityShiftController;
use App\Http\Controllers\Schedule\ScheduleCatalogController;
use App\Http\Controllers\Schedule\ScheduleConfigController;
use App\Http\Controllers\Schedule\ScheduleEventController;
use App\Http\Controllers\Schedule\ScheduleSubjectController;
use App\Http\Controllers\Schedule\ScheduleSummaryController;
use App\Http\Controllers\Schedule\SchoolDayTemplateController;
use App\Http\Controllers\Schedule\StudyPlanController;
use App\Http\Controllers\Schedule\TeacherContractController;
use App\Http\Controllers\Schedule\TeacherScheduleLayerController;
use App\Http\Controllers\Contracts\ContractClauseController;
use App\Http\Controllers\Contracts\ContractController;
use App\Http\Controllers\Contracts\ContractSignerController;
use App\Http\Controllers\Contracts\ContractTemplateController;
use App\Http\Controllers\CentroApuntes\CentroApuntesAsignaturaController;
use App\Http\Controllers\CentroApuntes\CentroApuntesCatalogsController;
use App\Http\Controllers\CentroApuntes\CentroApuntesDashboardController;
use App\Http\Controllers\CentroApuntes\CentroApuntesGlobalSearchController;
use App\Http\Controllers\CentroApuntes\CentroApuntesMaquinaController;
use App\Http\Controllers\CentroApuntes\CentroApuntesReportController;
use App\Http\Controllers\CentroApuntes\CentroApuntesSolicitudController;
use App\Http\Controllers\CentroApuntes\PanolEntregaController;
use App\Http\Controllers\CentroApuntes\PanolInsumoController;
use App\Http\Controllers\CentroApuntes\PanolMovimientoController;
use App\Http\Controllers\Pme\PmeActionController;
use App\Http\Controllers\Pme\PmeActivityController;
use App\Http\Controllers\Pme\PmeCatalogController;
use App\Http\Controllers\Pme\PmeDashboardController;
use App\Http\Controllers\Pme\PmeDimensionController;
use App\Http\Controllers\Pme\PmeEvidenceController;
use App\Http\Controllers\Pme\PmeGlobalSearchController;
use App\Http\Controllers\Pme\PmeIndicatorController;
use App\Http\Controllers\Pme\PmeMilestoneController;
use App\Http\Controllers\Pme\PmeObjectiveController;
use App\Http\Controllers\Pme\PmePlanController;
use App\Http\Controllers\Pme\PmeReflectiveMonitoringController;
use App\Http\Controllers\Pme\PmeReportController;
use App\Http\Controllers\Pme\PmeSepIncomeController;
use App\Http\Controllers\Pme\PmeStrategyController;
use App\Http\Controllers\Pme\PmeStudentSepController;
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
Route::get('/_debug/auth', [DebugAuthController::class, 'auth']);
Route::get('/deploy/status', [DeployController::class, 'status']);
Route::post('/deploy', [DeployController::class, 'run']);
Route::prefix('convivencia/public')->middleware('convivencia.installed')->group(function () {
    Route::post('/complaints', [ConvivenciaPublicComplaintController::class, 'store']);
    Route::get('/complaints/{folio}', [ConvivenciaPublicComplaintController::class, 'show']);
});

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

        Route::get('/organigram/catalogs', [OrganigramController::class, 'catalogs'])->middleware('permission:administrar_organigrama');
        Route::get('/organigram', [OrganigramController::class, 'index'])->middleware('permission:administrar_organigrama');
        Route::get('/organigram/{staff}', [OrganigramController::class, 'show'])->middleware('permission:administrar_organigrama');
        Route::put('/organigram/{staff}/relations', [OrganigramController::class, 'sync'])->middleware('permission:administrar_organigrama');
    });

    Route::prefix('students')->group(function () {
        Route::get('/catalogs', [StudentController::class, 'catalogs'])->middleware('permission:ver_estudiantes');
        Route::get('/export', [StudentController::class, 'export'])->middleware('permission:ver_estudiantes');

        Route::get('/levels', [EducationLevelController::class, 'index'])->middleware('permission:ver_estudiantes');
        Route::post('/levels', [EducationLevelController::class, 'store'])->middleware('permission:administrar_cursos_academicos');
        Route::put('/levels/{educationLevel}', [EducationLevelController::class, 'update'])->middleware('permission:administrar_cursos_academicos');
        Route::delete('/levels/{educationLevel}', [EducationLevelController::class, 'destroy'])->middleware('permission:administrar_cursos_academicos');

        Route::get('/academic-years', [AcademicYearController::class, 'index'])->middleware('permission:ver_estudiantes');
        Route::post('/academic-years', [AcademicYearController::class, 'store'])->middleware('permission:administrar_anos_academicos');
        Route::put('/academic-years/{academicYear}', [AcademicYearController::class, 'update'])->middleware('permission:administrar_anos_academicos');
        Route::put('/academic-years/{academicYear}/activate', [AcademicYearController::class, 'setActive'])->middleware('permission:administrar_anos_academicos');

        Route::get('/courses', [CourseSectionController::class, 'index'])->middleware('permission:ver_estudiantes');
        Route::post('/courses', [CourseSectionController::class, 'store'])->middleware('permission:administrar_cursos_academicos');
        Route::get('/courses/{courseSection}', [CourseSectionController::class, 'show'])->middleware('permission:ver_estudiantes');
        Route::put('/courses/{courseSection}', [CourseSectionController::class, 'update'])->middleware('permission:administrar_cursos_academicos');

        Route::get('/enrollment-management', [StudentEnrollmentManagementController::class, 'index'])->middleware('permission:gestionar_matriculas_estudiantes');
        Route::post('/enrollment-management/{studentEnrollment}/transfer', [StudentEnrollmentManagementController::class, 'transfer'])->middleware('permission:gestionar_matriculas_estudiantes');
        Route::post('/enrollment-management/{studentEnrollment}/withdraw', [StudentEnrollmentManagementController::class, 'withdraw'])->middleware('permission:gestionar_matriculas_estudiantes');
        Route::post('/enrollment-management/{studentEnrollment}/reenter', [StudentEnrollmentManagementController::class, 'reenter'])->middleware('permission:gestionar_matriculas_estudiantes');

        Route::post('/promotions', [StudentPromotionController::class, 'store'])->middleware('permission:promover_estudiantes');

        Route::get('/', [StudentController::class, 'index'])->middleware('permission:ver_estudiantes');
        Route::post('/', [StudentController::class, 'store'])->middleware('permission:crear_estudiantes');
        Route::get('/{studentProfile}', [StudentController::class, 'show'])->middleware('permission:ver_ficha_estudiante');
        Route::put('/{studentProfile}', [StudentController::class, 'update'])->middleware('permission:editar_estudiantes');

        Route::post('/{studentProfile}/enrollments', [StudentEnrollmentController::class, 'store'])->middleware('permission:gestionar_matriculas_estudiantes');
        Route::put('/enrollments/{studentEnrollment}', [StudentEnrollmentController::class, 'update'])->middleware('permission:gestionar_matriculas_estudiantes');
    });

    Route::prefix('porter')->group(function () {
        Route::get('/catalogs', PorterCatalogController::class)->middleware('permission:ver_porteria');
        Route::get('/dashboard', PorterDashboardController::class)->middleware('permission:ver_porteria');
        Route::get('/students', [PorterStudentController::class, 'index'])->middleware('permission:ver_porteria');
        Route::get('/students/{studentProfile}', [PorterStudentController::class, 'show'])->middleware('permission:ver_porteria');

        Route::get('/withdrawals', [PorterStudentWithdrawalController::class, 'index'])->middleware('permission:ver_historial_porteria');
        Route::post('/withdrawals', [PorterStudentWithdrawalController::class, 'store'])->middleware('permission:registrar_retiro_porteria');
        Route::get('/withdrawals/{porterStudentWithdrawal}', [PorterStudentWithdrawalController::class, 'show'])->middleware('permission:ver_historial_porteria');
        Route::post('/withdrawals/{porterStudentWithdrawal}/resolve', [PorterStudentWithdrawalController::class, 'resolve'])->middleware('permission:autorizar_retiros_porteria');
        Route::post('/withdrawals/{porterStudentWithdrawal}/annul', [PorterStudentWithdrawalController::class, 'annul'])->middleware('permission:autorizar_retiros_porteria');

        Route::get('/received-items', [PorterReceivedItemController::class, 'index'])->middleware('permission:ver_historial_porteria');
        Route::post('/received-items', [PorterReceivedItemController::class, 'store'])->middleware('permission:registrar_objetos_porteria');
        Route::get('/received-items/{porterReceivedItem}', [PorterReceivedItemController::class, 'show'])->middleware('permission:ver_historial_porteria');
        Route::put('/received-items/{porterReceivedItem}/status', [PorterReceivedItemController::class, 'updateStatus'])->middleware('permission:entregar_objetos_porteria');

        Route::get('/goods-movements', [PorterGoodsMovementController::class, 'index'])->middleware('permission:ver_historial_porteria');
        Route::post('/goods-movements', [PorterGoodsMovementController::class, 'store'])->middleware('permission:registrar_mercaderia_porteria');
        Route::get('/goods-movements/{porterGoodsMovement}', [PorterGoodsMovementController::class, 'show'])->middleware('permission:ver_historial_porteria');
        Route::put('/goods-movements/{porterGoodsMovement}/status', [PorterGoodsMovementController::class, 'updateStatus'])->middleware('permission:entregar_mercaderia_porteria');

        Route::get('/visits', [PorterVisitController::class, 'index'])->middleware('permission:ver_historial_porteria');
        Route::post('/visits', [PorterVisitController::class, 'store'])->middleware('permission:registrar_visitas_porteria');
        Route::put('/visits/{porterVisit}/exit', [PorterVisitController::class, 'exit'])->middleware('permission:registrar_visitas_porteria');

        Route::get('/external-services', [PorterExternalServiceEntryController::class, 'index'])->middleware('permission:ver_historial_porteria');
        Route::post('/external-services', [PorterExternalServiceEntryController::class, 'store'])->middleware('permission:registrar_proveedores_porteria');
        Route::put('/external-services/{porterExternalServiceEntry}/exit', [PorterExternalServiceEntryController::class, 'exit'])->middleware('permission:registrar_proveedores_porteria');

        Route::get('/daily-log', [PorterDailyLogEntryController::class, 'index'])->middleware('permission:ver_historial_porteria');
        Route::post('/daily-log', [PorterDailyLogEntryController::class, 'store'])->middleware('permission:registrar_bitacora_porteria');

        Route::get('/keys', [PorterKeyController::class, 'index'])->middleware('permission:ver_historial_porteria');
        Route::post('/keys', [PorterKeyController::class, 'store'])->middleware('permission:gestionar_llaves_porteria');
        Route::post('/keys/{porterKey}/loans', [PorterKeyController::class, 'loan'])->middleware('permission:gestionar_llaves_porteria');
        Route::post('/key-loans/{porterKeyLoan}/return', [PorterKeyController::class, 'returnLoan'])->middleware('permission:gestionar_llaves_porteria');

        Route::get('/reports', PorterReportController::class)->middleware('permission:ver_historial_porteria');
    });

    Route::prefix('tasks')->group(function () {
        Route::get('/catalogs', [TaskController::class, 'catalogs'])->middleware('permission:ver_tareas');
        Route::get('/stats', [TaskController::class, 'stats'])->middleware('permission:ver_tareas');
        Route::get('/assigners/can-assign', [TaskAssignerController::class, 'canAssign'])->middleware('permission:ver_tareas');
        Route::get('/assigners', [TaskAssignerController::class, 'index'])->middleware('permission:administrar_asignadores_tareas');
        Route::post('/assigners', [TaskAssignerController::class, 'store'])->middleware('permission:administrar_asignadores_tareas');
        Route::put('/assigners/{taskAssigner}', [TaskAssignerController::class, 'update'])->middleware('permission:administrar_asignadores_tareas');
        Route::delete('/assigners/{taskAssigner}', [TaskAssignerController::class, 'destroy'])->middleware('permission:administrar_asignadores_tareas');
        Route::get('/', [TaskController::class, 'index'])->middleware('permission:ver_tareas');
        Route::post('/', [TaskController::class, 'store'])->middleware('permission:ver_tareas');
        Route::get('/{task}', [TaskController::class, 'show'])->middleware('permission:ver_tareas');
        Route::put('/{task}', [TaskController::class, 'update'])->middleware('permission:ver_tareas');
        Route::delete('/{task}', [TaskController::class, 'destroy'])->middleware('permission:ver_tareas');
        Route::post('/{task}/subtasks', [TaskController::class, 'storeSubtask'])->middleware('permission:ver_tareas');
        Route::put('/{task}/status', [TaskController::class, 'updateStatus'])->middleware('permission:ver_tareas');
    });

    Route::prefix('pme-sep')->group(function () {
        Route::get('/catalogs', PmeCatalogController::class)->middleware('permission:ver_modulo_pme');
        Route::get('/dashboard', PmeDashboardController::class)->middleware('permission:ver_modulo_pme');
        Route::get('/search', PmeGlobalSearchController::class)->middleware('permission:ver_modulo_pme');

        Route::get('/plans', [PmePlanController::class, 'index'])->middleware('permission:ver_modulo_pme');
        Route::get('/plans/history', [PmePlanController::class, 'history'])->middleware('permission:ver_modulo_pme');
        Route::post('/plans', [PmePlanController::class, 'store'])->middleware('permission:crear_pme');
        Route::get('/plans/{plan}', [PmePlanController::class, 'show'])->middleware('permission:ver_modulo_pme');
        Route::put('/plans/{plan}', [PmePlanController::class, 'update'])->middleware('permission:editar_pme');
        Route::post('/plans/{plan}/activate', [PmePlanController::class, 'activate'])->middleware('permission:editar_pme');
        Route::post('/plans/{plan}/close', [PmePlanController::class, 'close'])->middleware('permission:cerrar_pme');
        Route::post('/plans/{plan}/archive', [PmePlanController::class, 'archive'])->middleware('permission:cerrar_pme');
        Route::post('/plans/{plan}/duplicate', [PmePlanController::class, 'duplicate'])->middleware('permission:crear_pme');
        Route::post('/cycles/{cycle}/close', [PmePlanController::class, 'closeCycle'])->middleware('permission:cerrar_pme');

        Route::get('/incomes', [PmeSepIncomeController::class, 'index'])->middleware('permission:ver_modulo_pme');
        Route::post('/incomes', [PmeSepIncomeController::class, 'store'])->middleware('permission:administrar_ingresos_sep');
        Route::put('/incomes/{income}', [PmeSepIncomeController::class, 'update'])->middleware('permission:administrar_ingresos_sep');
        Route::delete('/incomes/{income}', [PmeSepIncomeController::class, 'destroy'])->middleware('permission:administrar_ingresos_sep');

        Route::get('/students', [PmeStudentSepController::class, 'index'])->middleware('permission:ver_modulo_pme');
        Route::post('/students', [PmeStudentSepController::class, 'store'])->middleware('permission:cargar_estudiantes_sep');
        Route::put('/students/{studentSep}', [PmeStudentSepController::class, 'update'])->middleware('permission:cargar_estudiantes_sep');
        Route::post('/students/import', [PmeStudentSepController::class, 'import'])->middleware('permission:cargar_estudiantes_sep');

        Route::get('/dimensions', [PmeDimensionController::class, 'index'])->middleware('permission:ver_modulo_pme');
        Route::post('/dimensions', [PmeDimensionController::class, 'store'])->middleware('permission:administrar_configuracion_pme');
        Route::put('/dimensions/{dimension}', [PmeDimensionController::class, 'update'])->middleware('permission:administrar_configuracion_pme');
        Route::put('/dimensions/reorder', [PmeDimensionController::class, 'reorder'])->middleware('permission:administrar_configuracion_pme');

        Route::get('/objectives', [PmeObjectiveController::class, 'index'])->middleware('permission:ver_modulo_pme');
        Route::post('/objectives', [PmeObjectiveController::class, 'store'])->middleware('permission:crear_objetivos_pme');
        Route::put('/objectives/{objective}', [PmeObjectiveController::class, 'update'])->middleware('permission:editar_objetivos_pme');
        Route::get('/objectives/{objective}/measurements', [PmeObjectiveController::class, 'measurements'])->middleware('permission:ver_modulo_pme');
        Route::post('/objectives/{objective}/measurements', [PmeObjectiveController::class, 'storeMeasurement'])->middleware('permission:editar_objetivos_pme');
        Route::put('/goal-measurements/{measurement}', [PmeObjectiveController::class, 'updateMeasurement'])->middleware('permission:editar_objetivos_pme');

        Route::get('/strategies', [PmeStrategyController::class, 'index'])->middleware('permission:ver_modulo_pme');
        Route::post('/strategies', [PmeStrategyController::class, 'store'])->middleware('permission:crear_estrategias_pme');
        Route::put('/strategies/{strategy}', [PmeStrategyController::class, 'update'])->middleware('permission:editar_estrategias_pme');

        Route::get('/indicators', [PmeIndicatorController::class, 'index'])->middleware('permission:ver_modulo_pme');
        Route::post('/indicators', [PmeIndicatorController::class, 'store'])->middleware('permission:crear_indicadores_pme');
        Route::put('/indicators/{indicator}', [PmeIndicatorController::class, 'update'])->middleware('permission:crear_indicadores_pme');
        Route::get('/indicators/{indicator}/measurements', [PmeIndicatorController::class, 'measurements'])->middleware('permission:ver_modulo_pme');
        Route::post('/indicators/{indicator}/measurements', [PmeIndicatorController::class, 'storeMeasurement'])->middleware('permission:medir_indicadores_pme');

        Route::get('/actions', [PmeActionController::class, 'index'])->middleware('permission:ver_modulo_pme');
        Route::post('/actions', [PmeActionController::class, 'store'])->middleware('permission:crear_acciones_pme');
        Route::get('/actions/{action}', [PmeActionController::class, 'show'])->middleware('permission:ver_modulo_pme');
        Route::put('/actions/{action}', [PmeActionController::class, 'update'])->middleware('permission:editar_acciones_pme');
        Route::post('/actions/{action}/progress', [PmeActionController::class, 'progress'])->middleware('permission:editar_acciones_pme');
        Route::post('/actions/{action}/state', [PmeActionController::class, 'changeState'])->middleware('permission:editar_acciones_pme');
        Route::post('/actions/{action}/close', [PmeActionController::class, 'close'])->middleware('permission:cerrar_acciones_pme');
        Route::post('/actions/{action}/reopen', [PmeActionController::class, 'reopen'])->middleware('permission:editar_acciones_pme');

        Route::post('/activities', [PmeActivityController::class, 'store'])->middleware('permission:editar_acciones_pme');
        Route::put('/activities/{activity}', [PmeActivityController::class, 'update'])->middleware('permission:editar_acciones_pme');
        Route::delete('/activities/{activity}', [PmeActivityController::class, 'destroy'])->middleware('permission:editar_acciones_pme');

        Route::get('/milestones', [PmeMilestoneController::class, 'index'])->middleware('permission:ver_modulo_pme');
        Route::post('/milestones', [PmeMilestoneController::class, 'store'])->middleware('permission:crear_hitos_pme');
        Route::put('/milestones/{milestone}', [PmeMilestoneController::class, 'update'])->middleware('permission:crear_hitos_pme');
        Route::delete('/milestones/{milestone}', [PmeMilestoneController::class, 'destroy'])->middleware('permission:crear_hitos_pme');

        Route::get('/evidences', [PmeEvidenceController::class, 'index'])->middleware('permission:ver_modulo_pme');
        Route::post('/evidences', [PmeEvidenceController::class, 'store'])->middleware('permission:crear_evidencias_pme');
        Route::post('/evidences/{evidence}/review', [PmeEvidenceController::class, 'review'])->middleware('permission:revisar_evidencias_pme');
        Route::get('/evidences/{evidence}/download', [PmeEvidenceController::class, 'download'])->middleware('permission:ver_modulo_pme');
        Route::delete('/evidences/{evidence}', [PmeEvidenceController::class, 'destroy'])->middleware('permission:crear_evidencias_pme');

        Route::get('/monitorings', [PmeReflectiveMonitoringController::class, 'index'])->middleware('permission:ver_modulo_pme');
        Route::post('/monitorings', [PmeReflectiveMonitoringController::class, 'store'])->middleware('permission:registrar_monitoreo_reflexivo_pme');
        Route::put('/monitorings/{monitoring}', [PmeReflectiveMonitoringController::class, 'update'])->middleware('permission:registrar_monitoreo_reflexivo_pme');

        Route::post('/reports', PmeReportController::class)->middleware('permission:ver_reportes_pme');
    });

    Route::prefix('infirmary')->group(function () {
        Route::get('/catalogs', [InfirmaryCatalogController::class, 'catalogs'])->middleware('permission:ver_enfermeria');
        Route::get('/students', [InfirmaryCatalogController::class, 'students'])->middleware('permission:ver_enfermeria');
        Route::get('/dashboard', InfirmaryDashboardController::class)->middleware('permission:ver_enfermeria');

        Route::get('/attentions', [InfirmaryAttentionController::class, 'index'])->middleware('permission:ver_enfermeria');
        Route::post('/attentions', [InfirmaryAttentionController::class, 'store'])->middleware('permission:crear_atenciones_enfermeria');
        Route::get('/attentions/{attention}', [InfirmaryAttentionController::class, 'show'])->middleware('permission:ver_enfermeria');
        Route::put('/attentions/{attention}', [InfirmaryAttentionController::class, 'update'])->middleware('permission:editar_atenciones_enfermeria');
        Route::delete('/attentions/{attention}', [InfirmaryAttentionController::class, 'destroy'])->middleware('permission:eliminar_atenciones_enfermeria');
        Route::post('/attentions/{attention}/finalize', [InfirmaryAttentionController::class, 'finalize'])->middleware('permission:editar_atenciones_enfermeria');
        Route::post('/attentions/{attention}/documents', [InfirmaryDocumentController::class, 'storeForAttention'])->middleware('permission:editar_atenciones_enfermeria');

        Route::get('/student-history/{studentProfile}', InfirmaryStudentHistoryController::class)->middleware('permission:ver_enfermeria');

        Route::get('/medications', [InfirmaryMedicationInventoryController::class, 'index'])->middleware('permission:ver_enfermeria');
        Route::post('/medications', [InfirmaryMedicationInventoryController::class, 'store'])->middleware('permission:administrar_inventario_enfermeria');
        Route::get('/medications/{medication}', [InfirmaryMedicationInventoryController::class, 'show'])->middleware('permission:ver_enfermeria');
        Route::put('/medications/{medication}', [InfirmaryMedicationInventoryController::class, 'update'])->middleware('permission:administrar_inventario_enfermeria');
        Route::delete('/medications/{medication}', [InfirmaryMedicationInventoryController::class, 'destroy'])->middleware('permission:administrar_inventario_enfermeria');
        Route::post('/medications/{medication}/movements', [InfirmaryMedicationInventoryController::class, 'storeMovement'])->middleware('permission:administrar_inventario_enfermeria');

        Route::get('/medication-authorizations', [InfirmaryMedicationAuthorizationController::class, 'index'])->middleware('permission:ver_enfermeria');
        Route::post('/medication-authorizations', [InfirmaryMedicationAuthorizationController::class, 'store'])->middleware('permission:administrar_medicamentos_enfermeria');
        Route::get('/medication-authorizations/{authorization}', [InfirmaryMedicationAuthorizationController::class, 'show'])->middleware('permission:ver_enfermeria');
        Route::put('/medication-authorizations/{authorization}', [InfirmaryMedicationAuthorizationController::class, 'update'])->middleware('permission:administrar_medicamentos_enfermeria');
        Route::delete('/medication-authorizations/{authorization}', [InfirmaryMedicationAuthorizationController::class, 'destroy'])->middleware('permission:administrar_medicamentos_enfermeria');
        Route::post('/medication-authorizations/{authorization}/administrations', [InfirmaryMedicationAuthorizationController::class, 'storeAdministration'])->middleware('permission:administrar_medicamentos_enfermeria');
        Route::post('/medication-authorizations/{authorization}/documents', [InfirmaryDocumentController::class, 'storeForAuthorization'])->middleware('permission:administrar_medicamentos_enfermeria');

        Route::get('/accidents', [InfirmaryAccidentController::class, 'index'])->middleware('permission:ver_enfermeria');
        Route::post('/accidents', [InfirmaryAccidentController::class, 'store'])->middleware('permission:gestionar_accidentes_enfermeria');
        Route::get('/accidents/{accident}', [InfirmaryAccidentController::class, 'show'])->middleware('permission:ver_enfermeria');
        Route::put('/accidents/{accident}', [InfirmaryAccidentController::class, 'update'])->middleware('permission:gestionar_accidentes_enfermeria');
        Route::delete('/accidents/{accident}', [InfirmaryAccidentController::class, 'destroy'])->middleware('permission:gestionar_accidentes_enfermeria');
        Route::post('/accidents/{accident}/documents', [InfirmaryDocumentController::class, 'storeForAccident'])->middleware('permission:gestionar_accidentes_enfermeria');

        Route::get('/calls', [InfirmaryCallLogController::class, 'index'])->middleware('permission:ver_enfermeria');
        Route::post('/calls', [InfirmaryCallLogController::class, 'store'])->middleware('permission:crear_atenciones_enfermeria');
        Route::put('/calls/{call}', [InfirmaryCallLogController::class, 'update'])->middleware('permission:editar_atenciones_enfermeria');
        Route::delete('/calls/{call}', [InfirmaryCallLogController::class, 'destroy'])->middleware('permission:eliminar_atenciones_enfermeria');

        Route::get('/reports', InfirmaryReportController::class)->middleware('permission:ver_reportes_enfermeria');

        Route::get('/documents/{document}/download', [InfirmaryDocumentController::class, 'download'])->middleware('permission:ver_enfermeria');
        Route::delete('/documents/{document}', [InfirmaryDocumentController::class, 'destroy'])->middleware('permission:ver_enfermeria');
    });

    Route::prefix('apoyo-profesional')->group(function () {
        Route::get('/catalogs', [ApoyoProfesionalCatalogController::class, 'catalogs'])->middleware('permission:ver_modulo_apoyo_profesional');
        Route::get('/students', [ApoyoProfesionalCatalogController::class, 'students'])->middleware('permission:ver_modulo_apoyo_profesional');
        Route::get('/search', ApoyoProfesionalGlobalSearchController::class)->middleware('permission:ver_modulo_apoyo_profesional');
        Route::get('/dashboard', ApoyoProfesionalDashboardController::class)->middleware('permission:ver_modulo_apoyo_profesional');

        Route::get('/attentions', [ApoyoProfesionalAttentionController::class, 'index'])->middleware('permission:ver_modulo_apoyo_profesional');
        Route::post('/attentions', [ApoyoProfesionalAttentionController::class, 'store'])->middleware('permission:crear_atencion_apoyo_profesional');
        Route::get('/attentions/{attention}', [ApoyoProfesionalAttentionController::class, 'show'])->middleware('permission:ver_modulo_apoyo_profesional');
        Route::put('/attentions/{attention}', [ApoyoProfesionalAttentionController::class, 'update'])->middleware('permission:editar_atencion_propia_apoyo_profesional');
        Route::delete('/attentions/{attention}', [ApoyoProfesionalAttentionController::class, 'destroy'])->middleware('permission:eliminar_atencion_apoyo_profesional');
        Route::post('/attentions/{attention}/close', [ApoyoProfesionalAttentionController::class, 'close'])->middleware('permission:cerrar_caso_apoyo_profesional');

        Route::get('/student-history/{studentProfile}', ApoyoProfesionalStudentHistoryController::class)->middleware('permission:ver_modulo_apoyo_profesional');

        Route::get('/derivations', [ApoyoProfesionalDerivationController::class, 'index'])->middleware('permission:ver_modulo_apoyo_profesional');
        Route::post('/derivations', [ApoyoProfesionalDerivationController::class, 'store'])->middleware('permission:crear_derivacion_apoyo_profesional');
        Route::get('/derivations/{derivation}', [ApoyoProfesionalDerivationController::class, 'show'])->middleware('permission:ver_modulo_apoyo_profesional');
        Route::put('/derivations/{derivation}', [ApoyoProfesionalDerivationController::class, 'update'])->middleware('permission:crear_derivacion_apoyo_profesional');
        Route::post('/derivations/{derivation}/respond', [ApoyoProfesionalDerivationController::class, 'respond'])->middleware('permission:responder_derivacion_apoyo_profesional');

        Route::get('/follow-ups', [ApoyoProfesionalFollowUpController::class, 'index'])->middleware('permission:ver_modulo_apoyo_profesional');
        Route::post('/follow-ups', [ApoyoProfesionalFollowUpController::class, 'store'])->middleware('permission:crear_seguimiento_apoyo_profesional');
        Route::get('/follow-ups/{followUp}', [ApoyoProfesionalFollowUpController::class, 'show'])->middleware('permission:ver_modulo_apoyo_profesional');
        Route::put('/follow-ups/{followUp}', [ApoyoProfesionalFollowUpController::class, 'update'])->middleware('permission:crear_seguimiento_apoyo_profesional');
        Route::delete('/follow-ups/{followUp}', [ApoyoProfesionalFollowUpController::class, 'destroy'])->middleware('permission:crear_seguimiento_apoyo_profesional');

        Route::get('/plans', [ApoyoProfesionalPlanController::class, 'index'])->middleware('permission:ver_modulo_apoyo_profesional');
        Route::post('/plans', [ApoyoProfesionalPlanController::class, 'store'])->middleware('permission:crear_plan_apoyo_profesional');
        Route::get('/plans/{plan}', [ApoyoProfesionalPlanController::class, 'show'])->middleware('permission:ver_modulo_apoyo_profesional');
        Route::put('/plans/{plan}', [ApoyoProfesionalPlanController::class, 'update'])->middleware('permission:crear_plan_apoyo_profesional');

        Route::get('/interviews', [ApoyoProfesionalInterviewController::class, 'index'])->middleware('permission:ver_modulo_apoyo_profesional');
        Route::post('/interviews', [ApoyoProfesionalInterviewController::class, 'store'])->middleware('permission:crear_atencion_apoyo_profesional');
        Route::get('/interviews/{interview}', [ApoyoProfesionalInterviewController::class, 'show'])->middleware('permission:ver_modulo_apoyo_profesional');
        Route::put('/interviews/{interview}', [ApoyoProfesionalInterviewController::class, 'update'])->middleware('permission:crear_atencion_apoyo_profesional');
        Route::delete('/interviews/{interview}', [ApoyoProfesionalInterviewController::class, 'destroy'])->middleware('permission:crear_atencion_apoyo_profesional');

        Route::post('/attentions/{attention}/documents', [ApoyoProfesionalDocumentController::class, 'storeForAttention'])->middleware('permission:editar_atencion_propia_apoyo_profesional');
        Route::post('/derivations/{derivation}/documents', [ApoyoProfesionalDocumentController::class, 'storeForDerivation'])->middleware('permission:crear_derivacion_apoyo_profesional');
        Route::post('/follow-ups/{followUp}/documents', [ApoyoProfesionalDocumentController::class, 'storeForFollowUp'])->middleware('permission:crear_seguimiento_apoyo_profesional');
        Route::post('/plans/{plan}/documents', [ApoyoProfesionalDocumentController::class, 'storeForPlan'])->middleware('permission:crear_plan_apoyo_profesional');
        Route::post('/interviews/{interview}/documents', [ApoyoProfesionalDocumentController::class, 'storeForInterview'])->middleware('permission:crear_atencion_apoyo_profesional');
        Route::get('/documents/{document}/download', [ApoyoProfesionalDocumentController::class, 'download'])->middleware('permission:ver_modulo_apoyo_profesional');
        Route::delete('/documents/{document}', [ApoyoProfesionalDocumentController::class, 'destroy'])->middleware('permission:ver_modulo_apoyo_profesional');

        Route::get('/reports', ApoyoProfesionalReportController::class)->middleware('permission:ver_reportes_apoyo_profesional');
    });

    Route::prefix('convivencia')->middleware(['convivencia.installed', 'permission:ver_convivencia'])->group(function () {
        Route::get('/catalogs', [ConvivenciaCatalogController::class, 'catalogs']);
        Route::get('/students', [ConvivenciaCatalogController::class, 'students']);
        Route::get('/dashboard', ConvivenciaDashboardController::class);
        Route::post('/catalog-items', [ConvivenciaCatalogController::class, 'storeCatalogItem']);
        Route::put('/catalog-items/{catalogItem}', [ConvivenciaCatalogController::class, 'updateCatalogItem']);
        Route::post('/external-institutions', [ConvivenciaCatalogController::class, 'storeInstitution']);
        Route::put('/external-institutions/{externalInstitution}', [ConvivenciaCatalogController::class, 'updateInstitution']);

        Route::get('/cases', [ConvivenciaCaseController::class, 'index']);
        Route::post('/cases', [ConvivenciaCaseController::class, 'store']);
        Route::get('/cases/{case}', [ConvivenciaCaseController::class, 'show']);
        Route::put('/cases/{case}', [ConvivenciaCaseController::class, 'update']);
        Route::delete('/cases/{case}', [ConvivenciaCaseController::class, 'destroy']);
        Route::post('/cases/{case}/close', [ConvivenciaCaseController::class, 'close']);
        Route::post('/cases/{case}/follow-ups', [ConvivenciaCaseController::class, 'storeFollowUp']);
        Route::post('/cases/{case}/attachments', [ConvivenciaAttachmentController::class, 'storeForCase']);

        Route::get('/complaints', [ConvivenciaComplaintController::class, 'index']);
        Route::post('/complaints', [ConvivenciaComplaintController::class, 'store']);
        Route::get('/complaints/{complaint}', [ConvivenciaComplaintController::class, 'show']);
        Route::put('/complaints/{complaint}', [ConvivenciaComplaintController::class, 'update']);
        Route::delete('/complaints/{complaint}', [ConvivenciaComplaintController::class, 'destroy']);
        Route::post('/complaints/{complaint}/convert-to-case', [ConvivenciaComplaintController::class, 'convertToCase']);
        Route::post('/complaints/{complaint}/attachments', [ConvivenciaAttachmentController::class, 'storeForComplaint']);

        Route::get('/plans', [ConvivenciaPlanController::class, 'index']);
        Route::post('/plans', [ConvivenciaPlanController::class, 'store']);
        Route::get('/plans/{plan}', [ConvivenciaPlanController::class, 'show']);
        Route::put('/plans/{plan}', [ConvivenciaPlanController::class, 'update']);
        Route::delete('/plans/{plan}', [ConvivenciaPlanController::class, 'destroy']);
        Route::post('/plans/{plan}/attachments', [ConvivenciaAttachmentController::class, 'storeForPlan']);

        Route::get('/derivations', [ConvivenciaDerivationController::class, 'index']);
        Route::post('/derivations', [ConvivenciaDerivationController::class, 'store']);
        Route::get('/derivations/{derivation}', [ConvivenciaDerivationController::class, 'show']);
        Route::put('/derivations/{derivation}', [ConvivenciaDerivationController::class, 'update']);
        Route::delete('/derivations/{derivation}', [ConvivenciaDerivationController::class, 'destroy']);
        Route::post('/derivations/{derivation}/attachments', [ConvivenciaAttachmentController::class, 'storeForDerivation']);

        Route::get('/protocols', [ConvivenciaProtocolController::class, 'index']);
        Route::post('/protocols', [ConvivenciaProtocolController::class, 'store']);
        Route::get('/protocols/{protocol}', [ConvivenciaProtocolController::class, 'show']);
        Route::put('/protocols/{protocol}', [ConvivenciaProtocolController::class, 'update']);
        Route::get('/protocol-activations', [ConvivenciaProtocolController::class, 'activations']);
        Route::post('/protocol-activations', [ConvivenciaProtocolController::class, 'activate']);
        Route::get('/protocol-activations/{activation}', [ConvivenciaProtocolController::class, 'showActivation']);
        Route::put('/protocol-activations/{activation}', [ConvivenciaProtocolController::class, 'updateActivation']);
        Route::post('/protocol-activations/{activation}/attachments', [ConvivenciaAttachmentController::class, 'storeForProtocolActivation']);

        Route::get('/measures', [ConvivenciaMeasureController::class, 'index']);
        Route::post('/measures', [ConvivenciaMeasureController::class, 'store']);
        Route::get('/measures/{measure}', [ConvivenciaMeasureController::class, 'show']);
        Route::put('/measures/{measure}', [ConvivenciaMeasureController::class, 'update']);
        Route::delete('/measures/{measure}', [ConvivenciaMeasureController::class, 'destroy']);
        Route::post('/measures/{measure}/attachments', [ConvivenciaAttachmentController::class, 'storeForMeasure']);

        Route::get('/interviews', [ConvivenciaInterviewController::class, 'index']);
        Route::post('/interviews', [ConvivenciaInterviewController::class, 'store']);
        Route::get('/interviews/{interview}', [ConvivenciaInterviewController::class, 'show']);
        Route::put('/interviews/{interview}', [ConvivenciaInterviewController::class, 'update']);
        Route::delete('/interviews/{interview}', [ConvivenciaInterviewController::class, 'destroy']);
        Route::post('/interviews/{interview}/attachments', [ConvivenciaAttachmentController::class, 'storeForInterview']);

        Route::get('/daily-logs', [ConvivenciaDailyLogController::class, 'index']);
        Route::post('/daily-logs', [ConvivenciaDailyLogController::class, 'store']);
        Route::get('/daily-logs/{dailyLog}', [ConvivenciaDailyLogController::class, 'show']);
        Route::put('/daily-logs/{dailyLog}', [ConvivenciaDailyLogController::class, 'update']);
        Route::delete('/daily-logs/{dailyLog}', [ConvivenciaDailyLogController::class, 'destroy']);
        Route::post('/daily-logs/{dailyLog}/convert-to-case', [ConvivenciaDailyLogController::class, 'convertToCase']);
        Route::post('/daily-logs/{dailyLog}/convert-to-derivation', [ConvivenciaDailyLogController::class, 'convertToDerivation']);
        Route::post('/daily-logs/{dailyLog}/attachments', [ConvivenciaAttachmentController::class, 'storeForDailyLog']);

        Route::get('/sociograms', [ConvivenciaSociogramController::class, 'index']);
        Route::post('/sociograms', [ConvivenciaSociogramController::class, 'store']);
        Route::get('/sociograms/{sociogram}', [ConvivenciaSociogramController::class, 'show']);
        Route::put('/sociograms/{sociogram}', [ConvivenciaSociogramController::class, 'update']);
        Route::delete('/sociograms/{sociogram}', [ConvivenciaSociogramController::class, 'destroy']);

        Route::get('/idps', [ConvivenciaIdpsController::class, 'overview']);
        Route::post('/idps/periods', [ConvivenciaIdpsController::class, 'storePeriod']);
        Route::put('/idps/periods/{period}', [ConvivenciaIdpsController::class, 'updatePeriod']);
        Route::post('/idps/dimensions', [ConvivenciaIdpsController::class, 'storeDimension']);
        Route::put('/idps/dimensions/{dimension}', [ConvivenciaIdpsController::class, 'updateDimension']);
        Route::post('/idps/instruments', [ConvivenciaIdpsController::class, 'storeInstrument']);
        Route::put('/idps/instruments/{instrument}', [ConvivenciaIdpsController::class, 'updateInstrument']);
        Route::post('/idps/results', [ConvivenciaIdpsController::class, 'storeResult']);
        Route::put('/idps/results/{result}', [ConvivenciaIdpsController::class, 'updateResult']);

        Route::get('/reports/course', ConvivenciaReportController::class);

        Route::get('/attachments/{attachment}/download', [ConvivenciaAttachmentController::class, 'download']);
        Route::delete('/attachments/{attachment}', [ConvivenciaAttachmentController::class, 'destroy']);
    });

    Route::prefix('risk-prevention')->middleware('risk_prevention.installed')->group(function () {
        Route::get('/catalogs', RiskPreventionCatalogController::class)->middleware('permission:ver_prevencion_riesgos');
        Route::get('/dashboard', RiskPreventionDashboardController::class)->middleware('permission:ver_prevencion_riesgos');
        Route::get('/reports', RiskPreventionReportController::class)->middleware('permission:ver_prevencion_riesgos');

        Route::get('/extinguishers', [RiskPreventionFireExtinguisherController::class, 'index'])->middleware('permission:ver_prevencion_riesgos');
        Route::post('/extinguishers', [RiskPreventionFireExtinguisherController::class, 'store'])->middleware('permission:gestionar_prevencion_riesgos');
        Route::get('/extinguishers/{fireExtinguisher}', [RiskPreventionFireExtinguisherController::class, 'show'])->middleware('permission:ver_prevencion_riesgos');
        Route::put('/extinguishers/{fireExtinguisher}', [RiskPreventionFireExtinguisherController::class, 'update'])->middleware('permission:gestionar_prevencion_riesgos');
        Route::delete('/extinguishers/{fireExtinguisher}', [RiskPreventionFireExtinguisherController::class, 'destroy'])->middleware('permission:gestionar_prevencion_riesgos');

        Route::get('/accidents', [RiskPreventionAccidentController::class, 'index'])->middleware('permission:ver_prevencion_riesgos');
        Route::post('/accidents', [RiskPreventionAccidentController::class, 'store'])->middleware('permission:gestionar_prevencion_riesgos');
        Route::get('/accidents/{accident}', [RiskPreventionAccidentController::class, 'show'])->middleware('permission:ver_prevencion_riesgos');
        Route::put('/accidents/{accident}', [RiskPreventionAccidentController::class, 'update'])->middleware('permission:gestionar_prevencion_riesgos');
        Route::delete('/accidents/{accident}', [RiskPreventionAccidentController::class, 'destroy'])->middleware('permission:gestionar_prevencion_riesgos');
        Route::post('/accidents/{accident}/follow-ups', [RiskPreventionAccidentController::class, 'storeFollowUp'])->middleware('permission:gestionar_prevencion_riesgos');
        Route::delete('/accident-follow-ups/{accidentFollowUp}', [RiskPreventionAccidentController::class, 'destroyFollowUp'])->middleware('permission:gestionar_prevencion_riesgos');

        Route::get('/emergency-plans', [RiskPreventionEmergencyController::class, 'index'])->middleware('permission:ver_prevencion_riesgos');
        Route::post('/emergency-plans', [RiskPreventionEmergencyController::class, 'storePlan'])->middleware('permission:gestionar_prevencion_riesgos');
        Route::put('/emergency-plans/{emergencyPlan}', [RiskPreventionEmergencyController::class, 'updatePlan'])->middleware('permission:gestionar_prevencion_riesgos');
        Route::delete('/emergency-plans/{emergencyPlan}', [RiskPreventionEmergencyController::class, 'destroyPlan'])->middleware('permission:gestionar_prevencion_riesgos');
        Route::get('/emergency-plans/{emergencyPlan}/download', [RiskPreventionEmergencyController::class, 'downloadPlanDocument'])->middleware('permission:ver_prevencion_riesgos');
        Route::post('/emergency-plans/{emergencyPlan}/drills', [RiskPreventionEmergencyController::class, 'storeDrill'])->middleware('permission:gestionar_prevencion_riesgos');
        Route::delete('/emergency-drills/{emergencyDrill}', [RiskPreventionEmergencyController::class, 'destroyDrill'])->middleware('permission:gestionar_prevencion_riesgos');
        Route::get('/emergency-drills/{emergencyDrill}/download', [RiskPreventionEmergencyController::class, 'downloadDrillDocument'])->middleware('permission:ver_prevencion_riesgos');

        Route::get('/epp/items', [RiskPreventionEppController::class, 'itemsIndex'])->middleware('permission:ver_prevencion_riesgos');
        Route::post('/epp/items', [RiskPreventionEppController::class, 'storeItem'])->middleware('permission:gestionar_prevencion_riesgos');
        Route::put('/epp/items/{eppItem}', [RiskPreventionEppController::class, 'updateItem'])->middleware('permission:gestionar_prevencion_riesgos');
        Route::delete('/epp/items/{eppItem}', [RiskPreventionEppController::class, 'destroyItem'])->middleware('permission:gestionar_prevencion_riesgos');
        Route::get('/epp/deliveries', [RiskPreventionEppController::class, 'deliveriesIndex'])->middleware('permission:ver_prevencion_riesgos');
        Route::post('/epp/deliveries', [RiskPreventionEppController::class, 'storeDelivery'])->middleware('permission:gestionar_prevencion_riesgos');
        Route::put('/epp/deliveries/{eppDelivery}', [RiskPreventionEppController::class, 'updateDelivery'])->middleware('permission:gestionar_prevencion_riesgos');
        Route::delete('/epp/deliveries/{eppDelivery}', [RiskPreventionEppController::class, 'destroyDelivery'])->middleware('permission:gestionar_prevencion_riesgos');

        Route::get('/trainings', [RiskPreventionTrainingController::class, 'index'])->middleware('permission:ver_prevencion_riesgos');
        Route::post('/trainings', [RiskPreventionTrainingController::class, 'store'])->middleware('permission:gestionar_prevencion_riesgos');
        Route::put('/trainings/{training}', [RiskPreventionTrainingController::class, 'update'])->middleware('permission:gestionar_prevencion_riesgos');
        Route::delete('/trainings/{training}', [RiskPreventionTrainingController::class, 'destroy'])->middleware('permission:gestionar_prevencion_riesgos');
        Route::get('/trainings/{training}/evidence', [RiskPreventionTrainingController::class, 'downloadEvidence'])->middleware('permission:ver_prevencion_riesgos');

        Route::get('/documents', [RiskPreventionDocumentController::class, 'index'])->middleware('permission:ver_prevencion_riesgos');
        Route::post('/documents', [RiskPreventionDocumentController::class, 'store'])->middleware('permission:gestionar_prevencion_riesgos');
        Route::put('/documents/{document}', [RiskPreventionDocumentController::class, 'update'])->middleware('permission:gestionar_prevencion_riesgos');
        Route::delete('/documents/{document}', [RiskPreventionDocumentController::class, 'destroy'])->middleware('permission:gestionar_prevencion_riesgos');
        Route::get('/documents/{document}/download', [RiskPreventionDocumentController::class, 'download'])->middleware('permission:ver_prevencion_riesgos');
    });

    Route::prefix('biblioteca')->group(function () {
        Route::get('/catalogs', BibliotecaCatalogsController::class)->middleware('permission:ver_modulo_biblioteca');
        Route::get('/search', BibliotecaGlobalSearchController::class)->middleware('permission:ver_modulo_biblioteca');
        Route::get('/dashboard', BibliotecaDashboardController::class)->middleware('permission:ver_modulo_biblioteca');

        Route::get('/obras', [BibliotecaCatalogController::class, 'index'])->middleware('permission:ver_modulo_biblioteca');
        Route::post('/obras', [BibliotecaCatalogController::class, 'store'])->middleware('permission:crear_libros_biblioteca');
        Route::get('/obras/{obra}', [BibliotecaCatalogController::class, 'show'])->middleware('permission:ver_modulo_biblioteca');
        Route::put('/obras/{obra}', [BibliotecaCatalogController::class, 'update'])->middleware('permission:editar_libros_biblioteca');
        Route::delete('/obras/{obra}', [BibliotecaCatalogController::class, 'destroy'])->middleware('permission:eliminar_libros_biblioteca');

        Route::get('/ejemplares', [BibliotecaInventoryController::class, 'index'])->middleware('permission:ver_modulo_biblioteca');
        Route::post('/ejemplares', [BibliotecaInventoryController::class, 'store'])->middleware('permission:administrar_inventario_biblioteca');
        Route::get('/ejemplares/{ejemplar}', [BibliotecaInventoryController::class, 'show'])->middleware('permission:ver_modulo_biblioteca');
        Route::put('/ejemplares/{ejemplar}', [BibliotecaInventoryController::class, 'update'])->middleware('permission:administrar_inventario_biblioteca');
        Route::post('/ejemplares/{ejemplar}/audit', [BibliotecaInventoryController::class, 'audit'])->middleware('permission:administrar_inventario_biblioteca');
        Route::post('/ejemplares/{ejemplar}/damage', [BibliotecaInventoryController::class, 'markDamage'])->middleware('permission:administrar_inventario_biblioteca');
        Route::post('/ejemplares/{ejemplar}/loss', [BibliotecaInventoryController::class, 'markLoss'])->middleware('permission:administrar_inventario_biblioteca');
        Route::post('/ejemplares/{ejemplar}/deactivate', [BibliotecaInventoryController::class, 'deactivate'])->middleware('permission:administrar_inventario_biblioteca');

        Route::get('/prestamos', [BibliotecaLoanController::class, 'index'])->middleware('permission:ver_modulo_biblioteca');
        Route::post('/prestamos', [BibliotecaLoanController::class, 'store'])->middleware('permission:registrar_prestamos_biblioteca');
        Route::get('/prestamos/{prestamo}', [BibliotecaLoanController::class, 'show'])->middleware('permission:ver_modulo_biblioteca');
        Route::post('/prestamos/{prestamo}/renew', [BibliotecaLoanController::class, 'renew'])->middleware('permission:renovar_prestamos_biblioteca');
        Route::post('/prestamos/{prestamo}/return', [BibliotecaLoanController::class, 'return'])->middleware('permission:registrar_devoluciones_biblioteca');
        Route::post('/prestamos/{prestamo}/cancel', [BibliotecaLoanController::class, 'cancel'])->middleware('permission:gestionar_mora_biblioteca');

        Route::get('/reservas', [BibliotecaReservationController::class, 'index'])->middleware('permission:ver_modulo_biblioteca');
        Route::post('/reservas', [BibliotecaReservationController::class, 'store'])->middleware('permission:gestionar_reservas_biblioteca');
        Route::get('/reservas/{reserva}', [BibliotecaReservationController::class, 'show'])->middleware('permission:ver_modulo_biblioteca');
        Route::post('/reservas/{reserva}/approve', [BibliotecaReservationController::class, 'approve'])->middleware('permission:gestionar_reservas_biblioteca');
        Route::post('/reservas/{reserva}/reject', [BibliotecaReservationController::class, 'reject'])->middleware('permission:gestionar_reservas_biblioteca');
        Route::post('/reservas/{reserva}/checkout', [BibliotecaReservationController::class, 'checkout'])->middleware('permission:gestionar_reservas_biblioteca');
        Route::post('/reservas/{reserva}/return', [BibliotecaReservationController::class, 'registerReturn'])->middleware('permission:gestionar_reservas_biblioteca');
        Route::post('/reservas/{reserva}/cancel', [BibliotecaReservationController::class, 'cancel'])->middleware('permission:gestionar_reservas_biblioteca');

        Route::get('/plan-lector', [BibliotecaPlanLectorController::class, 'index'])->middleware('permission:ver_modulo_biblioteca');
        Route::post('/plan-lector', [BibliotecaPlanLectorController::class, 'store'])->middleware('permission:gestionar_plan_lector_biblioteca');
        Route::get('/plan-lector/{planLector}', [BibliotecaPlanLectorController::class, 'show'])->middleware('permission:ver_modulo_biblioteca');
        Route::put('/plan-lector/{planLector}', [BibliotecaPlanLectorController::class, 'update'])->middleware('permission:gestionar_plan_lector_biblioteca');
        Route::post('/plan-lector/{planLector}/mass-loan', [BibliotecaPlanLectorController::class, 'massLoan'])->middleware('permission:gestionar_plan_lector_biblioteca');

        Route::get('/espacios', [BibliotecaSpaceController::class, 'spaces'])->middleware('permission:ver_modulo_biblioteca');
        Route::post('/espacios', [BibliotecaSpaceController::class, 'storeSpace'])->middleware('permission:gestionar_uso_espacios_biblioteca');
        Route::put('/espacios/{espacio}', [BibliotecaSpaceController::class, 'updateSpace'])->middleware('permission:gestionar_uso_espacios_biblioteca');
        Route::get('/uso-espacios', [BibliotecaSpaceController::class, 'usages'])->middleware('permission:ver_modulo_biblioteca');
        Route::get('/uso-espacios/calendar', [BibliotecaSpaceController::class, 'calendar'])->middleware('permission:ver_modulo_biblioteca');
        Route::post('/uso-espacios', [BibliotecaSpaceController::class, 'storeUsage'])->middleware('permission:gestionar_uso_espacios_biblioteca');
        Route::put('/uso-espacios/{usoEspacio}', [BibliotecaSpaceController::class, 'updateUsage'])->middleware('permission:gestionar_uso_espacios_biblioteca');
        Route::post('/uso-espacios/{usoEspacio}/status/{status}', [BibliotecaSpaceController::class, 'transition'])->middleware('permission:gestionar_uso_espacios_biblioteca');

        Route::get('/reportes', BibliotecaReportController::class)->middleware('permission:ver_estadisticas_biblioteca');
    });

    Route::prefix('informatica')->group(function () {
        Route::get('/catalogs', InformaticaCatalogController::class)->middleware('permission:informatica.ver');
        Route::get('/dashboard', InformaticaDashboardController::class)->middleware('permission:informatica.dashboard');

        Route::get('/equipos', [ItEquipmentController::class, 'index'])->middleware('permission:informatica.equipos.ver');
        Route::post('/equipos', [ItEquipmentController::class, 'store'])->middleware('permission:informatica.equipos.crear');
        Route::get('/equipos/{equipment}', [ItEquipmentController::class, 'show'])->middleware('permission:informatica.equipos.ver');
        Route::post('/equipos/{equipment}', [ItEquipmentController::class, 'update'])->middleware('permission:informatica.equipos.editar');
        Route::post('/equipos/{equipment}/status', [ItEquipmentController::class, 'changeStatus'])->middleware('permission:informatica.equipos.editar');
        Route::delete('/equipos/{equipment}', [ItEquipmentController::class, 'destroy'])->middleware('permission:informatica.equipos.eliminar');
        Route::post('/equipos/{equipment}/attachments', [ItEquipmentAttachmentController::class, 'storeForEquipment'])->middleware('permission:informatica.equipos.editar');

        Route::get('/prestamos', [ItEquipmentLoanController::class, 'index'])->middleware('permission:informatica.prestamos.ver');
        Route::post('/prestamos', [ItEquipmentLoanController::class, 'store'])->middleware('permission:informatica.prestamos.crear');
        Route::get('/prestamos/{loan}', [ItEquipmentLoanController::class, 'show'])->middleware('permission:informatica.prestamos.ver');
        Route::post('/prestamos/{loan}/return', [ItEquipmentLoanController::class, 'registerReturn'])->middleware('permission:informatica.prestamos.devolver');
        Route::post('/prestamos/{loan}/cancel', [ItEquipmentLoanController::class, 'cancel'])->middleware('permission:informatica.prestamos.cancelar');
        Route::post('/prestamos/{loan}/attachments', [ItEquipmentAttachmentController::class, 'storeForLoan'])->middleware('permission:informatica.prestamos.devolver');

        Route::get('/mantenciones', [ItEquipmentMaintenanceController::class, 'index'])->middleware('permission:informatica.mantenciones.ver');
        Route::post('/mantenciones', [ItEquipmentMaintenanceController::class, 'store'])->middleware('permission:informatica.mantenciones.crear');
        Route::get('/mantenciones/{report}', [ItEquipmentMaintenanceController::class, 'show'])->middleware('permission:informatica.mantenciones.ver');
        Route::post('/mantenciones/{report}', [ItEquipmentMaintenanceController::class, 'update'])->middleware('permission:informatica.mantenciones.editar');
        Route::post('/mantenciones/{report}/close', [ItEquipmentMaintenanceController::class, 'close'])->middleware('permission:informatica.mantenciones.cerrar');
        Route::post('/mantenciones/{report}/attachments', [ItEquipmentAttachmentController::class, 'storeForMaintenance'])->middleware('permission:informatica.mantenciones.editar');

        Route::get('/adjuntos/{attachment}/download', [ItEquipmentAttachmentController::class, 'download'])->middleware('permission:informatica.ver');
        Route::delete('/adjuntos/{attachment}', [ItEquipmentAttachmentController::class, 'destroy'])->middleware('permission:informatica.ver');

        Route::get('/reportes', InformaticaReportController::class)->middleware('permission:informatica.reportes.ver');
    });

    Route::prefix('remuneraciones')->group(function () {
        Route::get('/catalogs', [RemunerationModuleController::class, 'catalogs'])->middleware('permission:remuneraciones.ver');
        Route::get('/dashboard', [RemunerationModuleController::class, 'dashboard'])->middleware('permission:remuneraciones.ver');
        Route::get('/export', [RemunerationModuleController::class, 'export'])->middleware('permission:remuneraciones.reportes.exportar');
        Route::get('/payrolls/pdf-data', [RemunerationModuleController::class, 'payrollPdfData'])->middleware('permission:remuneraciones.reportes.exportar');
        Route::post('/payrolls/calculate', [RemunerationModuleController::class, 'calculate'])->middleware('permission:remuneraciones.liquidaciones.calcular');
        Route::post('/payrolls/bulk-calculate', [RemunerationModuleController::class, 'bulkCalculate'])->middleware('permission:remuneraciones.liquidaciones.calcular');
        Route::post('/payrolls/{payroll}/approve', [RemunerationModuleController::class, 'approve'])->middleware('permission:remuneraciones.liquidaciones.aprobar');
        Route::post('/payrolls/{payroll}/observe', [RemunerationModuleController::class, 'observe'])->middleware('permission:remuneraciones.liquidaciones.aprobar');
        Route::post('/payrolls/{payroll}/annul', [RemunerationModuleController::class, 'annul'])->middleware('permission:remuneraciones.liquidaciones.aprobar');
        Route::post('/payrolls/{payroll}/pay', [RemunerationModuleController::class, 'pay'])->middleware('permission:remuneraciones.pagos.gestionar');
        Route::post('/payrolls/{payroll}/centralize', [RemunerationModuleController::class, 'centralize'])->middleware('permission:remuneraciones.contabilidad.centralizar');
        Route::post('/periods/{period}/close', [RemunerationModuleController::class, 'closePeriod'])->middleware('permission:remuneraciones.periodos.cerrar');
        Route::post('/periods/{period}/reopen', [RemunerationModuleController::class, 'reopenPeriod'])->middleware('permission:remuneraciones.periodos.cerrar');
        Route::get('/resources/{resource}', [RemunerationModuleController::class, 'index'])->middleware('permission:remuneraciones.ver');
        Route::get('/resources/{resource}/{record}', [RemunerationModuleController::class, 'show'])->middleware('permission:remuneraciones.ver');
        Route::post('/resources/{resource}', [RemunerationModuleController::class, 'store'])->middleware('permission:remuneraciones.ver');
        Route::put('/resources/{resource}/{record}', [RemunerationModuleController::class, 'update'])->middleware('permission:remuneraciones.ver');
        Route::delete('/resources/{resource}/{record}', [RemunerationModuleController::class, 'destroy'])->middleware('permission:remuneraciones.ver');
    });

    Route::prefix('contabilidad')->group(function () {
        Route::get('/catalogs', [AccountingModuleController::class, 'catalogs'])->middleware('permission:contabilidad.ver');
        Route::get('/dashboard', [AccountingModuleController::class, 'dashboard'])->middleware('permission:contabilidad.ver');
        Route::get('/reportes', [AccountingModuleController::class, 'reports'])->middleware('permission:contabilidad.ver');
        Route::get('/export/{report}', [AccountingModuleController::class, 'export'])->middleware('permission:contabilidad.reportes.exportar');
        Route::get('/resources/{resource}', [AccountingModuleController::class, 'index'])->middleware('permission:contabilidad.ver');
        Route::get('/resources/{resource}/{record}', [AccountingModuleController::class, 'show'])->middleware('permission:contabilidad.ver');
        Route::post('/resources/{resource}', [AccountingModuleController::class, 'store'])->middleware('permission:contabilidad.ver');
        Route::put('/resources/{resource}/{record}', [AccountingModuleController::class, 'update'])->middleware('permission:contabilidad.ver');
        Route::delete('/resources/{resource}/{record}', [AccountingModuleController::class, 'destroy'])->middleware('permission:contabilidad.ver');
    });

    Route::prefix('centro-apuntes')->group(function () {
        Route::get('/catalogs', CentroApuntesCatalogsController::class);
        Route::get('/search', CentroApuntesGlobalSearchController::class)->middleware('permission:ver_modulo_centro_apuntes');
        Route::get('/dashboard', CentroApuntesDashboardController::class)->middleware('permission:ver_modulo_centro_apuntes');

        Route::get('/solicitudes', [CentroApuntesSolicitudController::class, 'index'])->middleware('permission:ver_modulo_centro_apuntes');
        Route::post('/solicitudes', [CentroApuntesSolicitudController::class, 'store'])->middleware('permission:crear_solicitud_impresion');
        Route::get('/solicitudes/{solicitud}', [CentroApuntesSolicitudController::class, 'show'])->middleware('permission:ver_modulo_centro_apuntes');
        Route::put('/solicitudes/{solicitud}', [CentroApuntesSolicitudController::class, 'update'])->middleware('permission:editar_solicitud_impresion');
        Route::delete('/solicitudes/{solicitud}', [CentroApuntesSolicitudController::class, 'destroy'])->middleware('permission:eliminar_solicitud_impresion');
        Route::post('/solicitudes/{solicitud}/status', [CentroApuntesSolicitudController::class, 'changeStatus'])->middleware('permission:cambiar_estado_solicitud_impresion');
        Route::post('/solicitudes/{solicitud}/deliver', [CentroApuntesSolicitudController::class, 'registerDelivery'])->middleware('permission:registrar_entrega_centro_apuntes');

        Route::get('/asignaturas', [CentroApuntesAsignaturaController::class, 'index'])->middleware('permission:ver_modulo_centro_apuntes');
        Route::post('/asignaturas', [CentroApuntesAsignaturaController::class, 'store'])->middleware('permission:administrar_asignaturas_centro_apuntes');
        Route::put('/asignaturas/{subject}', [CentroApuntesAsignaturaController::class, 'update'])->middleware('permission:administrar_asignaturas_centro_apuntes');
        Route::delete('/asignaturas/{subject}', [CentroApuntesAsignaturaController::class, 'destroy'])->middleware('permission:administrar_asignaturas_centro_apuntes');

        Route::get('/maquinas', [CentroApuntesMaquinaController::class, 'index'])->middleware('permission:ver_modulo_centro_apuntes');
        Route::post('/maquinas', [CentroApuntesMaquinaController::class, 'store'])->middleware('permission:administrar_maquinas_centro_apuntes');
        Route::get('/maquinas/{machine}', [CentroApuntesMaquinaController::class, 'show'])->middleware('permission:ver_modulo_centro_apuntes');
        Route::put('/maquinas/{machine}', [CentroApuntesMaquinaController::class, 'update'])->middleware('permission:administrar_maquinas_centro_apuntes');
        Route::delete('/maquinas/{machine}', [CentroApuntesMaquinaController::class, 'destroy'])->middleware('permission:administrar_maquinas_centro_apuntes');

        Route::get('/insumos', [PanolInsumoController::class, 'index'])->middleware('permission:ver_modulo_centro_apuntes');
        Route::post('/insumos', [PanolInsumoController::class, 'store'])->middleware('permission:administrar_inventario_panol');
        Route::get('/insumos/{supply}', [PanolInsumoController::class, 'show'])->middleware('permission:ver_modulo_centro_apuntes');
        Route::put('/insumos/{supply}', [PanolInsumoController::class, 'update'])->middleware('permission:administrar_inventario_panol');
        Route::delete('/insumos/{supply}', [PanolInsumoController::class, 'destroy'])->middleware('permission:administrar_inventario_panol');

        Route::get('/movimientos', [PanolMovimientoController::class, 'index'])->middleware('permission:ver_modulo_centro_apuntes');
        Route::post('/movimientos', [PanolMovimientoController::class, 'store'])->middleware('permission:registrar_movimientos_panol');

        Route::get('/entregas', [PanolEntregaController::class, 'index'])->middleware('permission:ver_modulo_centro_apuntes');
        Route::post('/entregas', [PanolEntregaController::class, 'store'])->middleware('permission:ver_modulo_centro_apuntes');
        Route::get('/entregas/{delivery}', [PanolEntregaController::class, 'show'])->middleware('permission:ver_modulo_centro_apuntes');
        Route::put('/entregas/{delivery}', [PanolEntregaController::class, 'update'])->middleware('permission:ver_modulo_centro_apuntes');
        Route::delete('/entregas/{delivery}', [PanolEntregaController::class, 'destroy'])->middleware('permission:aprobar_entregas_panol');
        Route::post('/entregas/{delivery}/approve', [PanolEntregaController::class, 'approve'])->middleware('permission:aprobar_entregas_panol');
        Route::post('/entregas/{delivery}/reject', [PanolEntregaController::class, 'reject'])->middleware('permission:aprobar_entregas_panol');
        Route::post('/entregas/{delivery}/annul', [PanolEntregaController::class, 'annul'])->middleware('permission:aprobar_entregas_panol');
        Route::post('/entregas/{delivery}/deliver', [PanolEntregaController::class, 'deliver'])->middleware('permission:aprobar_entregas_panol');

        Route::get('/reportes', CentroApuntesReportController::class)->middleware('permission:ver_reportes_centro_apuntes');
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

    // Dependencias y reservas
    Route::prefix('spaces')->group(function () {
        Route::get('/dependencies/catalogs', [MaintenanceDependencyController::class, 'catalogs'])
            ->middleware('permission:ver_dependencias');
        Route::get('/dependencies/approvers', [MaintenanceDependencyController::class, 'approversIndex'])
            ->middleware('permission:ver_dependencias');
        Route::get('/dependencies', [MaintenanceDependencyController::class, 'index'])
            ->middleware('permission:ver_dependencias');
        Route::post('/dependencies', [MaintenanceDependencyController::class, 'store'])
            ->middleware('permission:crear_dependencias');
        Route::get('/dependencies/{maintenanceDependency}', [MaintenanceDependencyController::class, 'show'])
            ->middleware('permission:ver_dependencias');
        Route::put('/dependencies/{maintenanceDependency}/approvers', [MaintenanceDependencyController::class, 'updateApprovers'])
            ->middleware('permission:editar_dependencias');
        Route::post('/dependencies/{maintenanceDependency}', [MaintenanceDependencyController::class, 'update'])
            ->middleware('permission:editar_dependencias');
        Route::put('/dependencies/{maintenanceDependency}', [MaintenanceDependencyController::class, 'update'])
            ->middleware('permission:editar_dependencias');
        Route::delete('/dependencies/{maintenanceDependency}', [MaintenanceDependencyController::class, 'destroy'])
            ->middleware('permission:eliminar_dependencias');

        Route::get('/dependency-types', [DependencyTypeController::class, 'index'])
            ->middleware('permission:ver_dependencias');
        Route::post('/dependency-types', [DependencyTypeController::class, 'store'])
            ->middleware('permission:crear_dependencias');
        Route::get('/dependency-types/{dependencyType}', [DependencyTypeController::class, 'show'])
            ->middleware('permission:ver_dependencias');
        Route::put('/dependency-types/{dependencyType}', [DependencyTypeController::class, 'update'])
            ->middleware('permission:editar_dependencias');
        Route::delete('/dependency-types/{dependencyType}', [DependencyTypeController::class, 'destroy'])
            ->middleware('permission:eliminar_dependencias');

        Route::get('/reservations/catalogs', [DependencyReservationController::class, 'catalogs'])
            ->middleware('permission:ver_reservas');
        Route::get('/reservations', [DependencyReservationController::class, 'index'])
            ->middleware('permission:ver_reservas');
        Route::post('/reservations', [DependencyReservationController::class, 'store'])
            ->middleware('permission:crear_reservas');
        Route::get('/reservations/{dependencyReservation}', [DependencyReservationController::class, 'show'])
            ->middleware('permission:ver_reservas');
        Route::put('/reservations/{dependencyReservation}', [DependencyReservationController::class, 'update'])
            ->middleware('permission:editar_reservas');
        Route::put('/reservations/{dependencyReservation}/cancel', [DependencyReservationController::class, 'cancel'])
            ->middleware('permission:cancelar_reservas');
        Route::put('/reservations/{dependencyReservation}/approve', [DependencyReservationController::class, 'approve'])
            ->middleware('permission:ver_reservas');
        Route::put('/reservations/{dependencyReservation}/reject', [DependencyReservationController::class, 'reject'])
            ->middleware('permission:ver_reservas');
        Route::get('/calendar/events', [DependencyReservationController::class, 'events'])
            ->middleware('permission:ver_reservas');
        Route::get('/statistics/catalogs', [SpaceStatisticsController::class, 'catalogs'])
            ->middleware('permission:ver_estadisticas_espacios');
        Route::get('/statistics', [SpaceStatisticsController::class, 'index'])
            ->middleware('permission:ver_estadisticas_espacios');
    });

    Route::prefix('security')->group(function () {
        Route::get('/catalogs', SecurityCatalogController::class);
        Route::get('/dashboard', [SecurityDashboardController::class, 'index']);
        Route::get('/notifications', [SecurityNotificationController::class, 'index']);
        Route::put('/notifications/{securityNotification}/read', [SecurityNotificationController::class, 'markAsRead']);

        Route::get('/shifts', [SecurityShiftController::class, 'index']);
        Route::post('/shifts', [SecurityShiftController::class, 'store']);
        Route::get('/shifts/{securityShift}', [SecurityShiftController::class, 'show']);
        Route::put('/shifts/{securityShift}', [SecurityShiftController::class, 'update']);
        Route::post('/shifts/{securityShift}/start', [SecurityShiftController::class, 'start']);
        Route::post('/shifts/{securityShift}/finish', [SecurityShiftController::class, 'finish']);
        Route::post('/shifts/{securityShift}/rounds', [SecurityShiftController::class, 'storeRound']);

        Route::get('/incidents', [SecurityIncidentController::class, 'index']);
        Route::get('/incidents/{securityIncident}', [SecurityIncidentController::class, 'show']);
        Route::put('/incidents/{securityIncident}', [SecurityIncidentController::class, 'update']);
        Route::post('/incidents/{securityIncident}/comments', [SecurityIncidentController::class, 'storeComment']);
    });

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

    // Inventario
    Route::prefix('inventory')->group(function () {
        // Catálogos
        Route::get('/categories', [InventoryCategoryController::class, 'index'])
            ->middleware('permission:ver_inventario');
        Route::post('/categories', [InventoryCategoryController::class, 'store'])
            ->middleware('permission:administrar_categorias_inventario');
        Route::get('/categories/{category}', [InventoryCategoryController::class, 'show'])
            ->middleware('permission:ver_inventario');
        Route::put('/categories/{category}', [InventoryCategoryController::class, 'update'])
            ->middleware('permission:administrar_categorias_inventario');
        Route::delete('/categories/{category}', [InventoryCategoryController::class, 'destroy'])
            ->middleware('permission:administrar_categorias_inventario');

        Route::get('/subcategories', [InventorySubcategoryController::class, 'index'])
            ->middleware('permission:ver_inventario');
        Route::post('/subcategories', [InventorySubcategoryController::class, 'store'])
            ->middleware('permission:administrar_categorias_inventario');
        Route::get('/subcategories/{subcategory}', [InventorySubcategoryController::class, 'show'])
            ->middleware('permission:ver_inventario');
        Route::put('/subcategories/{subcategory}', [InventorySubcategoryController::class, 'update'])
            ->middleware('permission:administrar_categorias_inventario');
        Route::delete('/subcategories/{subcategory}', [InventorySubcategoryController::class, 'destroy'])
            ->middleware('permission:administrar_categorias_inventario');

        Route::get('/suppliers', [InventorySupplierController::class, 'index'])
            ->middleware('permission:ver_inventario');
        Route::post('/suppliers', [InventorySupplierController::class, 'store'])
            ->middleware('permission:crear_inventario');
        Route::get('/suppliers/{supplier}', [InventorySupplierController::class, 'show'])
            ->middleware('permission:ver_inventario');
        Route::put('/suppliers/{supplier}', [InventorySupplierController::class, 'update'])
            ->middleware('permission:editar_inventario');
        Route::delete('/suppliers/{supplier}', [InventorySupplierController::class, 'destroy'])
            ->middleware('permission:eliminar_inventario');

        // Bienes
        Route::get('/items/catalogs', [InventoryItemController::class, 'catalogs'])
            ->middleware('permission:ver_inventario');
        Route::get('/items', [InventoryItemController::class, 'index'])
            ->middleware('permission:ver_inventario');
        Route::post('/items', [InventoryItemController::class, 'store'])
            ->middleware('permission:crear_inventario');
        Route::get('/items/{item}', [InventoryItemController::class, 'show'])
            ->middleware('permission:ver_inventario');
        Route::put('/items/{item}', [InventoryItemController::class, 'update'])
            ->middleware('permission:editar_inventario');
        Route::delete('/items/{item}', [InventoryItemController::class, 'destroy'])
            ->middleware('permission:eliminar_inventario');

        // Fotos
        Route::post('/items/{item}/photos', [InventoryItemPhotoController::class, 'store'])
            ->middleware('permission:editar_inventario');
        Route::delete('/photos/{photo}', [InventoryItemPhotoController::class, 'destroy'])
            ->middleware('permission:eliminar_documentos_inventario');
        Route::put('/photos/{photo}/main', [InventoryItemPhotoController::class, 'setMain'])
            ->middleware('permission:editar_inventario');

        // Documentos
        Route::post('/items/{item}/documents', [InventoryItemDocumentController::class, 'store'])
            ->middleware('permission:subir_documentos_inventario');
        Route::delete('/documents/{document}', [InventoryItemDocumentController::class, 'destroy'])
            ->middleware('permission:eliminar_documentos_inventario');

        // Movimientos
        Route::get('/items/{item}/movements', [InventoryMovementController::class, 'index'])
            ->middleware('permission:ver_inventario');
        Route::post('/items/{item}/move', [InventoryMovementController::class, 'move'])
            ->middleware('permission:mover_inventario');

        // Stock (insumos)
        Route::get('/items/{item}/stock', [InventoryStockController::class, 'index'])
            ->middleware('permission:ver_inventario');
        Route::post('/items/{item}/stock', [InventoryStockController::class, 'store'])
            ->middleware('permission:mover_inventario');

        // Reportes
        Route::get('/reports/dashboard', [InventoryReportController::class, 'dashboard'])
            ->middleware('permission:ver_reportes_inventario');
        Route::get('/reports/low-stock', [InventoryReportController::class, 'lowStock'])
            ->middleware('permission:ver_reportes_inventario');
    });

    Route::prefix('schedule')->group(function () {
        Route::get('/catalogs', ScheduleCatalogController::class)
            ->middleware('permission:ver_horarios');

        Route::get('/config', [ScheduleConfigController::class, 'show'])
            ->middleware('permission:ver_horarios');
        Route::put('/config', [ScheduleConfigController::class, 'update'])
            ->middleware('permission:configurar_horarios');

        Route::get('/jornadas', [SchoolDayTemplateController::class, 'index'])
            ->middleware('permission:ver_horarios');
        Route::post('/jornadas', [SchoolDayTemplateController::class, 'store'])
            ->middleware('permission:configurar_jornadas');
        Route::get('/jornadas/{jornada}', [SchoolDayTemplateController::class, 'show'])
            ->middleware('permission:ver_horarios');
        Route::put('/jornadas/{jornada}', [SchoolDayTemplateController::class, 'update'])
            ->middleware('permission:configurar_jornadas');
        Route::delete('/jornadas/{jornada}', [SchoolDayTemplateController::class, 'destroy'])
            ->middleware('permission:configurar_jornadas');
        Route::post('/jornadas/{jornada}/duplicate', [SchoolDayTemplateController::class, 'duplicate'])
            ->middleware('permission:configurar_jornadas');
        Route::post('/jornadas/{jornada}/assign-levels', [SchoolDayTemplateController::class, 'assignLevels'])
            ->middleware('permission:configurar_jornadas');
        Route::post('/jornadas/{jornada}/assign-courses', [SchoolDayTemplateController::class, 'assignCourses'])
            ->middleware('permission:configurar_jornadas');

        Route::get('/subjects', [ScheduleSubjectController::class, 'index'])
            ->middleware('permission:ver_horarios');
        Route::post('/subjects', [ScheduleSubjectController::class, 'store'])
            ->middleware('permission:configurar_plan_estudio');
        Route::put('/subjects/{subject}', [ScheduleSubjectController::class, 'update'])
            ->middleware('permission:configurar_plan_estudio');
        Route::delete('/subjects/{subject}', [ScheduleSubjectController::class, 'destroy'])
            ->middleware('permission:configurar_plan_estudio');

        Route::get('/study-plans', [StudyPlanController::class, 'index'])
            ->middleware('permission:ver_horarios');
        Route::post('/study-plans', [StudyPlanController::class, 'store'])
            ->middleware('permission:configurar_plan_estudio');
        Route::put('/study-plans/{studyPlan}', [StudyPlanController::class, 'update'])
            ->middleware('permission:configurar_plan_estudio');
        Route::post('/study-plans/{studyPlan}/subjects', [StudyPlanController::class, 'storeSubject'])
            ->middleware('permission:configurar_plan_estudio');
        Route::put('/study-plans/{studyPlan}/subjects/{subjectId}', [StudyPlanController::class, 'updateSubject'])
            ->middleware('permission:configurar_plan_estudio');

        Route::get('/teacher-contracts', [TeacherContractController::class, 'index'])
            ->middleware('permission:ver_horarios');
        Route::post('/teacher-contracts', [TeacherContractController::class, 'store'])
            ->middleware('permission:configurar_contratos_docentes');
        Route::put('/teacher-contracts/{teacherContract}', [TeacherContractController::class, 'update'])
            ->middleware('permission:configurar_contratos_docentes');

        Route::get('/teachers/{teacher}/layers', [TeacherScheduleLayerController::class, 'index'])
            ->middleware('permission:ver_horarios');
        Route::post('/teachers/{teacher}/layers', [TeacherScheduleLayerController::class, 'store'])
            ->middleware('permission:editar_horarios');
        Route::put('/layers/{layer}', [TeacherScheduleLayerController::class, 'update'])
            ->middleware('permission:editar_horarios');
        Route::delete('/layers/{layer}', [TeacherScheduleLayerController::class, 'destroy'])
            ->middleware('permission:editar_horarios');

        Route::get('/events', [ScheduleEventController::class, 'index'])
            ->middleware('permission:ver_horarios');
        Route::post('/events', [ScheduleEventController::class, 'store'])
            ->middleware('permission:editar_horarios');
        Route::post('/events/validate-preview', [ScheduleEventController::class, 'previewValidation'])
            ->middleware('permission:editar_horarios');
        Route::put('/events/{event}', [ScheduleEventController::class, 'update'])
            ->middleware('permission:editar_horarios');
        Route::delete('/events/{event}', [ScheduleEventController::class, 'destroy'])
            ->middleware('permission:editar_horarios');
        Route::post('/events/{event}/move', [ScheduleEventController::class, 'move'])
            ->middleware('permission:editar_horarios');
        Route::post('/events/{event}/validate', [ScheduleEventController::class, 'validateEvent'])
            ->middleware('permission:editar_horarios');

        Route::get('/teachers/{teacher}/summary', [ScheduleSummaryController::class, 'teacher'])
            ->middleware('permission:ver_reportes_carga_horaria');
        Route::get('/courses/{course}/summary', [ScheduleSummaryController::class, 'course'])
            ->middleware('permission:ver_reportes_carga_horaria');
        Route::get('/courses/{course}/study-plan-progress', [ScheduleSummaryController::class, 'studyPlanProgress'])
            ->middleware('permission:ver_horarios');
        Route::get('/conflicts', [ScheduleSummaryController::class, 'conflicts'])
            ->middleware('permission:ver_horarios');
    });

    // Funcionarios
    Route::get('/staff/catalogs', [StaffController::class, 'catalogs'])
        ->middleware('permission:ver_funcionarios');
    Route::get('/staff', [StaffController::class, 'index'])
        ->middleware('permission:ver_funcionarios');
    Route::post('/staff', [StaffController::class, 'store'])
        ->middleware('permission:gestionar_funcionarios');
    Route::get('/staff/departments/catalogs', [DepartmentController::class, 'catalogs'])
        ->middleware('permission:administrar_departamentos');
    Route::get('/staff/departments', [DepartmentController::class, 'index'])
        ->middleware('permission:ver_funcionarios');
    Route::post('/staff/departments', [DepartmentController::class, 'store'])
        ->middleware('permission:administrar_departamentos');
    Route::get('/staff/departments/{department}', [DepartmentController::class, 'show'])
        ->middleware('permission:ver_funcionarios');
    Route::put('/staff/departments/{department}', [DepartmentController::class, 'update'])
        ->middleware('permission:administrar_departamentos');
    Route::delete('/staff/departments/{department}', [DepartmentController::class, 'destroy'])
        ->middleware('permission:administrar_departamentos');
    Route::put('/staff/departments/{department}/active', [DepartmentController::class, 'setActive'])
        ->middleware('permission:administrar_departamentos');

    // Permisos del personal
    Route::get('/staff/permissions/catalogs', [PermissionRequestController::class, 'catalogs']);
    Route::get('/staff/permissions/dashboard', [PermissionDashboardController::class, 'index'])
        ->middleware('permission:ver_permisos_personal');
    Route::get('/staff/permissions/reports', [PermissionReportController::class, 'index'])
        ->middleware('permission:ver_permisos_personal');
    Route::get('/staff/permissions', [PermissionRequestController::class, 'index']);
    Route::post('/staff/permissions', [PermissionRequestController::class, 'store'])
        ->middleware('permission:solicitar_permisos_personal');
    Route::get('/staff/permissions/{permissionRequest}', [PermissionRequestController::class, 'show']);
    Route::put('/staff/permissions/{permissionRequest}', [PermissionRequestController::class, 'update'])
        ->middleware('permission:solicitar_permisos_personal');
    Route::post('/staff/permissions/{permissionRequest}/submit', [PermissionRequestController::class, 'submit'])
        ->middleware('permission:solicitar_permisos_personal');
    Route::post('/staff/permissions/{permissionRequest}/approve', [PermissionRequestController::class, 'approve'])
        ->middleware('permission:ver_permisos_personal');
    Route::post('/staff/permissions/{permissionRequest}/reject', [PermissionRequestController::class, 'reject'])
        ->middleware('permission:ver_permisos_personal');
    Route::post('/staff/permissions/{permissionRequest}/observe', [PermissionRequestController::class, 'observe'])
        ->middleware('permission:ver_permisos_personal');
    Route::post('/staff/permissions/{permissionRequest}/return', [PermissionRequestController::class, 'returnToEmployee'])
        ->middleware('permission:ver_permisos_personal');
    Route::post('/staff/permissions/{permissionRequest}/cancel', [PermissionRequestController::class, 'cancel'])
        ->middleware('permission:ver_permisos_personal');
    Route::post('/staff/permissions/{permissionRequest}/execute', [PermissionRequestController::class, 'execute'])
        ->middleware('permission:ver_permisos_personal');
    Route::post('/staff/permissions/{permissionRequest}/documents', [PermissionRequestDocumentController::class, 'store'])
        ->middleware('permission:ver_permisos_personal');
    Route::delete('/staff/permissions/documents/{document}', [PermissionRequestDocumentController::class, 'destroy'])
        ->middleware('permission:ver_permisos_personal');
    Route::get('/staff/permissions/documents/{document}/download', [PermissionRequestDocumentController::class, 'download'])
        ->middleware('permission:ver_permisos_personal');
    Route::put('/staff/permissions/documents/{document}/validation', [PermissionRequestDocumentController::class, 'validateDocument'])
        ->middleware('permission:ver_permisos_personal');
    Route::put('/staff/permissions/{permissionRequest}/replacements', [PermissionRequestReplacementController::class, 'sync'])
        ->middleware('permission:ver_permisos_personal');

    Route::get('/staff/permission-types', [PermissionTypeController::class, 'index'])
        ->middleware('permission:ver_permisos_personal');
    Route::post('/staff/permission-types', [PermissionTypeController::class, 'store'])
        ->middleware('permission:ver_permisos_personal');
    Route::get('/staff/permission-types/{permissionType}', [PermissionTypeController::class, 'show'])
        ->middleware('permission:ver_permisos_personal');
    Route::put('/staff/permission-types/{permissionType}', [PermissionTypeController::class, 'update'])
        ->middleware('permission:ver_permisos_personal');
    Route::put('/staff/permission-types/{permissionType}/active', [PermissionTypeController::class, 'setActive'])
        ->middleware('permission:ver_permisos_personal');
    Route::get('/staff/permission-type-watchers/catalogs', [PermissionTypeWatcherController::class, 'catalogs'])
        ->middleware('permission:administrar_destinatarios_permisos_personal');
    Route::get('/staff/permission-types/{permissionType}/watchers', [PermissionTypeWatcherController::class, 'index'])
        ->middleware('permission:administrar_destinatarios_permisos_personal');
    Route::put('/staff/permission-types/{permissionType}/watchers', [PermissionTypeWatcherController::class, 'sync'])
        ->middleware('permission:administrar_destinatarios_permisos_personal');
    Route::get('/staff/permission-watchers/summary', [StaffPermissionWatcherController::class, 'summary'])
        ->middleware('permission:administrar_destinatarios_permisos_personal');
    Route::get('/staff/{staff}/permission-watchers', [StaffPermissionWatcherController::class, 'index'])
        ->middleware('permission:administrar_destinatarios_permisos_personal');
    Route::get('/staff/{staff}/permission-summary', [PermissionRequestController::class, 'staffSummary'])
        ->middleware('permission:ver_permisos_personal');
    Route::get('/staff/{staff}', [StaffController::class, 'show'])
        ->middleware('permission:ver_funcionarios');
    Route::put('/staff/{staff}', [StaffController::class, 'update'])
        ->middleware('permission:gestionar_funcionarios');
    Route::put('/staff/{staff}/permission-watchers', [StaffPermissionWatcherController::class, 'sync'])
        ->middleware('permission:administrar_destinatarios_permisos_personal');
    Route::delete('/staff/{staff}', [StaffController::class, 'destroy'])
        ->middleware('permission:eliminar_funcionarios');
    Route::put('/staff/{staff}/active', [StaffController::class, 'setActive'])
        ->middleware('permission:gestionar_funcionarios');
    Route::post('/staff/{staff}/documents', [StaffDocumentController::class, 'store'])
        ->middleware('permission:subir_documentos_funcionarios');
    Route::delete('/staff/documents/{document}', [StaffDocumentController::class, 'destroy'])
        ->middleware('permission:subir_documentos_funcionarios');

    // Calendario y fechas relevantes
    Route::get('/relevant-calendar/catalogs', [CalendarEventController::class, 'catalogs'])
        ->middleware('permission:ver_calendario_fechas_relevantes');
    Route::get('/relevant-calendar/overview', [CalendarEventController::class, 'overview'])
        ->middleware('permission:ver_calendario_fechas_relevantes');
    Route::get('/relevant-calendar/feed', [CalendarEventController::class, 'calendarFeed'])
        ->middleware('permission:ver_calendario_fechas_relevantes');
    Route::get('/relevant-calendar/events', [CalendarEventController::class, 'index'])
        ->middleware('permission:ver_calendario_fechas_relevantes');
    Route::post('/relevant-calendar/events', [CalendarEventController::class, 'store'])
        ->middleware('permission:ver_calendario_fechas_relevantes');
    Route::get('/relevant-calendar/events/{calendarEvent}', [CalendarEventController::class, 'show'])
        ->middleware('permission:ver_calendario_fechas_relevantes');
    Route::put('/relevant-calendar/events/{calendarEvent}', [CalendarEventController::class, 'update'])
        ->middleware('permission:ver_calendario_fechas_relevantes');
    Route::delete('/relevant-calendar/events/{calendarEvent}', [CalendarEventController::class, 'destroy'])
        ->middleware('permission:ver_calendario_fechas_relevantes');

    Route::post('/relevant-calendar/events/{calendarEvent}/attachments', [CalendarEventAttachmentController::class, 'store'])
        ->middleware('permission:ver_calendario_fechas_relevantes');
    Route::get('/relevant-calendar/attachments/{calendarEventAttachment}/download', [CalendarEventAttachmentController::class, 'download'])
        ->middleware('permission:ver_calendario_fechas_relevantes');
    Route::delete('/relevant-calendar/attachments/{calendarEventAttachment}', [CalendarEventAttachmentController::class, 'destroy'])
        ->middleware('permission:ver_calendario_fechas_relevantes');

    Route::get('/relevant-calendar/process-types', [CalendarProcessTypeController::class, 'index'])
        ->middleware('permission:ver_calendario_fechas_relevantes');
    Route::post('/relevant-calendar/process-types', [CalendarProcessTypeController::class, 'store'])
        ->middleware('permission:ver_calendario_fechas_relevantes');
    Route::put('/relevant-calendar/process-types/{calendarProcessType}', [CalendarProcessTypeController::class, 'update'])
        ->middleware('permission:ver_calendario_fechas_relevantes');
    Route::delete('/relevant-calendar/process-types/{calendarProcessType}', [CalendarProcessTypeController::class, 'destroy'])
        ->middleware('permission:ver_calendario_fechas_relevantes');
    Route::put('/relevant-calendar/process-types/{calendarProcessType}/active', [CalendarProcessTypeController::class, 'setActive'])
        ->middleware('permission:ver_calendario_fechas_relevantes');

    Route::get('/relevant-calendar/institutions', [CalendarInstitutionController::class, 'index'])
        ->middleware('permission:ver_calendario_fechas_relevantes');
    Route::post('/relevant-calendar/institutions', [CalendarInstitutionController::class, 'store'])
        ->middleware('permission:ver_calendario_fechas_relevantes');
    Route::put('/relevant-calendar/institutions/{calendarInstitution}', [CalendarInstitutionController::class, 'update'])
        ->middleware('permission:ver_calendario_fechas_relevantes');
    Route::delete('/relevant-calendar/institutions/{calendarInstitution}', [CalendarInstitutionController::class, 'destroy'])
        ->middleware('permission:ver_calendario_fechas_relevantes');
    Route::put('/relevant-calendar/institutions/{calendarInstitution}/active', [CalendarInstitutionController::class, 'setActive'])
        ->middleware('permission:ver_calendario_fechas_relevantes');

    // Contratos
    Route::get('/contracts/catalogs', [ContractController::class, 'catalogs'])
        ->middleware('permission:ver_contratos');
    Route::post('/contracts/preview', [ContractController::class, 'preview'])
        ->middleware('permission:gestionar_contratos');
    Route::get('/contracts', [ContractController::class, 'index'])
        ->middleware('permission:ver_contratos');
    Route::post('/contracts', [ContractController::class, 'store'])
        ->middleware('permission:gestionar_contratos');
    Route::get('/contracts/{contract}', [ContractController::class, 'show'])
        ->middleware('permission:ver_contratos');
    Route::put('/contracts/{contract}', [ContractController::class, 'update'])
        ->middleware('permission:gestionar_contratos');
    Route::delete('/contracts/{contract}', [ContractController::class, 'destroy'])
        ->middleware('permission:eliminar_contratos');
    Route::put('/contracts/{contract}/status', [ContractController::class, 'setStatus'])
        ->middleware('permission:gestionar_contratos');
    Route::get('/contracts/{contract}/export-word', [ContractController::class, 'downloadWord'])
        ->middleware('permission:exportar_contratos');

    Route::get('/contract-templates/catalogs', [ContractTemplateController::class, 'catalogs'])
        ->middleware('permission:administrar_plantillas_contrato');
    Route::post('/contract-templates/preview', [ContractTemplateController::class, 'preview'])
        ->middleware('permission:administrar_plantillas_contrato');
    Route::get('/contract-templates', [ContractTemplateController::class, 'index'])
        ->middleware('permission:ver_contratos');
    Route::post('/contract-templates', [ContractTemplateController::class, 'store'])
        ->middleware('permission:administrar_plantillas_contrato');
    Route::get('/contract-templates/{contractTemplate}', [ContractTemplateController::class, 'show'])
        ->middleware('permission:ver_contratos');
    Route::put('/contract-templates/{contractTemplate}', [ContractTemplateController::class, 'update'])
        ->middleware('permission:administrar_plantillas_contrato');
    Route::delete('/contract-templates/{contractTemplate}', [ContractTemplateController::class, 'destroy'])
        ->middleware('permission:administrar_plantillas_contrato');
    Route::put('/contract-templates/{contractTemplate}/active', [ContractTemplateController::class, 'setActive'])
        ->middleware('permission:administrar_plantillas_contrato');

    Route::get('/contract-clauses/catalogs', [ContractClauseController::class, 'catalogs'])
        ->middleware('permission:administrar_clausulas_contrato');
    Route::post('/contract-clauses/preview', [ContractClauseController::class, 'preview'])
        ->middleware('permission:administrar_clausulas_contrato');
    Route::get('/contract-clauses', [ContractClauseController::class, 'index'])
        ->middleware('permission:ver_contratos');
    Route::post('/contract-clauses', [ContractClauseController::class, 'store'])
        ->middleware('permission:administrar_clausulas_contrato');
    Route::get('/contract-clauses/{contractClause}', [ContractClauseController::class, 'show'])
        ->middleware('permission:ver_contratos');
    Route::put('/contract-clauses/{contractClause}', [ContractClauseController::class, 'update'])
        ->middleware('permission:administrar_clausulas_contrato');
    Route::delete('/contract-clauses/{contractClause}', [ContractClauseController::class, 'destroy'])
        ->middleware('permission:administrar_clausulas_contrato');
    Route::put('/contract-clauses/{contractClause}/active', [ContractClauseController::class, 'setActive'])
        ->middleware('permission:administrar_clausulas_contrato');

    Route::get('/contract-signers/catalogs', [ContractSignerController::class, 'catalogs'])
        ->middleware('permission:administrar_firmas_contrato');
    Route::get('/contract-signers', [ContractSignerController::class, 'index'])
        ->middleware('permission:ver_contratos');
    Route::post('/contract-signers', [ContractSignerController::class, 'store'])
        ->middleware('permission:administrar_firmas_contrato');
    Route::get('/contract-signers/{contractSigner}', [ContractSignerController::class, 'show'])
        ->middleware('permission:ver_contratos');
    Route::post('/contract-signers/{contractSigner}', [ContractSignerController::class, 'update'])
        ->middleware('permission:administrar_firmas_contrato');
    Route::delete('/contract-signers/{contractSigner}', [ContractSignerController::class, 'destroy'])
        ->middleware('permission:administrar_firmas_contrato');
    Route::put('/contract-signers/{contractSigner}/active', [ContractSignerController::class, 'setActive'])
        ->middleware('permission:administrar_firmas_contrato');
});
