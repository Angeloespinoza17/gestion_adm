<?php

use App\Http\Controllers\Accounting\AccountingBudgetExecutionController;
use App\Http\Controllers\Accounting\AccountingModuleController;
use App\Http\Controllers\Accounting\AccountingSubsidyController;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\RelojControlController;
use App\Http\Controllers\Admin\RoleImpersonationController;
use App\Http\Controllers\Admin\SuperAdminDashboardController;
use App\Http\Controllers\Admin\SuperAdminLogbookReviewController;
use App\Http\Controllers\Admin\SuperAdminSupplyRequestController;
use App\Http\Controllers\Admin\SuperAdminUsageLevelController;
use App\Http\Controllers\Api\Messaging\ConversationController as MessagingConversationController;
use App\Http\Controllers\Api\Messaging\MessageController as MessagingMessageController;
use App\Http\Controllers\Api\Messaging\MessagingController;
use App\Http\Controllers\Api\Messaging\ReceiptController as MessagingReceiptController;
use App\Http\Controllers\Api\Messaging\UploadController as MessagingUploadController;
use App\Http\Controllers\APIController;
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
use App\Http\Controllers\Attendance\AttendanceController;
use App\Http\Controllers\Attendance\AttendanceManagementCaseController;
use App\Http\Controllers\Attendance\AttendanceManagementConfigurationController;
use App\Http\Controllers\Attendance\AttendanceManagementDashboardController;
use App\Http\Controllers\Attendance\AttendanceStatisticsController;
use App\Http\Controllers\Attendance\AttendanceStatisticsExportController;
use App\Http\Controllers\Attendance\AttendanceStatisticsManagementController;
use App\Http\Controllers\Attendance\MonthlyAttendanceImportController;
use App\Http\Controllers\CargoController;
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
use App\Http\Controllers\ContactMessageController;
use App\Http\Controllers\Contracts\ContractClauseController;
use App\Http\Controllers\Contracts\ContractController;
use App\Http\Controllers\Contracts\ContractSignerController;
use App\Http\Controllers\Contracts\ContractTemplateController;
use App\Http\Controllers\Convivencia\ConvivenciaAnnualPlanController;
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
use App\Http\Controllers\Convivencia\ConvivenciaPlanActionController;
use App\Http\Controllers\Convivencia\ConvivenciaPlanActivityController;
use App\Http\Controllers\Convivencia\ConvivenciaPlanCalendarController;
use App\Http\Controllers\Convivencia\ConvivenciaPlanController;
use App\Http\Controllers\Convivencia\ConvivenciaPlanVersionController;
use App\Http\Controllers\Convivencia\ConvivenciaProtocolController;
use App\Http\Controllers\Convivencia\ConvivenciaProtocolPartController;
use App\Http\Controllers\Convivencia\ConvivenciaProtocolRuntimeController;
use App\Http\Controllers\Convivencia\ConvivenciaPublicComplaintController;
use App\Http\Controllers\Convivencia\ConvivenciaReferenceController;
use App\Http\Controllers\Convivencia\ConvivenciaReportController;
use App\Http\Controllers\Convivencia\ConvivenciaSociogramController;
use App\Http\Controllers\DeployController;
use App\Http\Controllers\Grades\AnnualGradeImportController;
use App\Http\Controllers\Grades\GradeStatisticsController;
use App\Http\Controllers\Grades\GradeStudentStatisticsController;
use App\Http\Controllers\HomeDashboardController;
use App\Http\Controllers\HumanResources\HrAbsenceController;
use App\Http\Controllers\HumanResources\HrImportController;
use App\Http\Controllers\HumanResources\HrRecruitmentController;
use App\Http\Controllers\Infirmary\InfirmaryAccidentController;
use App\Http\Controllers\Infirmary\InfirmaryAttentionCategoryController;
use App\Http\Controllers\Infirmary\InfirmaryAttentionController;
use App\Http\Controllers\Infirmary\InfirmaryCallLogController;
use App\Http\Controllers\Infirmary\InfirmaryCatalogController;
use App\Http\Controllers\Infirmary\InfirmaryDailyLogController;
use App\Http\Controllers\Infirmary\InfirmaryDashboardController;
use App\Http\Controllers\Infirmary\InfirmaryDocumentController;
use App\Http\Controllers\Infirmary\InfirmaryMedicationAuthorizationController;
use App\Http\Controllers\Infirmary\InfirmaryMedicationInventoryController;
use App\Http\Controllers\Infirmary\InfirmaryReportController;
use App\Http\Controllers\Infirmary\InfirmaryStaffAttentionController;
use App\Http\Controllers\Infirmary\InfirmaryStudentHistoryController;
use App\Http\Controllers\Informatica\InformaticaCatalogController;
use App\Http\Controllers\Informatica\InformaticaDashboardController;
use App\Http\Controllers\Informatica\InformaticaReportController;
use App\Http\Controllers\Informatica\ItEquipmentAttachmentController;
use App\Http\Controllers\Informatica\ItEquipmentController;
use App\Http\Controllers\Informatica\ItEquipmentLoanController;
use App\Http\Controllers\Informatica\ItEquipmentMaintenanceController;
use App\Http\Controllers\Inspectoria\InspectoriaAttentionController;
use App\Http\Controllers\Inspectoria\InspectoriaCatalogController;
use App\Http\Controllers\Inspectoria\InspectoriaCourseAssignmentController;
use App\Http\Controllers\Inspectoria\InspectoriaDailyLogController;
use App\Http\Controllers\Inspectoria\InspectoriaPassController;
use App\Http\Controllers\Inspectoria\InspectoriaStatisticsController;
use App\Http\Controllers\Inspectoria\InspectoriaStudentController;
use App\Http\Controllers\Inspectoria\InspectoriaWithdrawalController;
use App\Http\Controllers\InternalCommunications\InternalAnnouncementController;
use App\Http\Controllers\InternalNotificationController;
use App\Http\Controllers\Inventory\InventoryCategoryController;
use App\Http\Controllers\Inventory\InventoryItemController;
use App\Http\Controllers\Inventory\InventoryItemDocumentController;
use App\Http\Controllers\Inventory\InventoryItemPhotoController;
use App\Http\Controllers\Inventory\InventoryManagementController;
use App\Http\Controllers\Inventory\InventoryMovementController;
use App\Http\Controllers\Inventory\InventoryReportController;
use App\Http\Controllers\Inventory\InventoryStockController;
use App\Http\Controllers\Inventory\InventorySubcategoryController;
use App\Http\Controllers\Inventory\SupplierController as InventorySupplierController;
use App\Http\Controllers\Library\BibliotecaCatalogController;
use App\Http\Controllers\Library\BibliotecaCatalogsController;
use App\Http\Controllers\Library\BibliotecaDashboardController;
use App\Http\Controllers\Library\BibliotecaGlobalSearchController;
use App\Http\Controllers\Library\BibliotecaInventoryController;
use App\Http\Controllers\Library\BibliotecaLoanController;
use App\Http\Controllers\Library\BibliotecaManagementController;
use App\Http\Controllers\Library\BibliotecaPassController;
use App\Http\Controllers\Library\BibliotecaPlanLectorController;
use App\Http\Controllers\Library\BibliotecaReportController;
use App\Http\Controllers\Library\BibliotecaReservationController;
use App\Http\Controllers\Library\BibliotecaSpaceController;
use App\Http\Controllers\Library\BibliotecaTemporaryBorrowerController;
use App\Http\Controllers\Library\BibliotecaTextbookController;
use App\Http\Controllers\Library\OpenLibraryController;
use App\Http\Controllers\MaintenanceAnnualPlanController;
use App\Http\Controllers\MaintenanceDependencyController;
use App\Http\Controllers\MaintenanceReportController;
use App\Http\Controllers\MaintenanceVisitController;
use App\Http\Controllers\MaintenanceVisitPlanningController;
use App\Http\Controllers\MaintenanceWorkOrderController;
use App\Http\Controllers\ManagedDocumentController;
use App\Http\Controllers\MeController;
use App\Http\Controllers\NewsPostController;
use App\Http\Controllers\Operational\OperationalStaffLogbookController;
use App\Http\Controllers\Operational\OperationalTransferController;
use App\Http\Controllers\Operational\OperationalTransferDocumentController;
use App\Http\Controllers\Operational\OperationalTransferImportController;
use App\Http\Controllers\Operational\OperationalTransferProviderController;
use App\Http\Controllers\Operational\OperationalTransferQuoteController;
use App\Http\Controllers\Operational\OperationalTransferReportController;
use App\Http\Controllers\OrganigramController;
use App\Http\Controllers\Orientation\OrientationActionController;
use App\Http\Controllers\Orientation\OrientationActivityController;
use App\Http\Controllers\Orientation\OrientationCalendarController;
use App\Http\Controllers\Orientation\OrientationCalendarizationController;
use App\Http\Controllers\Orientation\OrientationEvidenceController;
use App\Http\Controllers\Orientation\OrientationPlanController;
use App\Http\Controllers\Orientation\OrientationRelatedPlanController;
use App\Http\Controllers\Orientation\OrientationStatisticsController;
use App\Http\Controllers\PermissionController;
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
use App\Http\Controllers\Porter\PorterCatalogController;
use App\Http\Controllers\Porter\PorterDailyLogEntryController;
use App\Http\Controllers\Porter\PorterDashboardController;
use App\Http\Controllers\Porter\PorterExternalServiceEntryController;
use App\Http\Controllers\Porter\PorterGoodsMovementController;
use App\Http\Controllers\Porter\PorterKeyController;
use App\Http\Controllers\Porter\PorterReceivedItemController;
use App\Http\Controllers\Porter\PorterReportController;
use App\Http\Controllers\Porter\PorterStudentController;
use App\Http\Controllers\Porter\PorterStudentWithdrawalController;
use App\Http\Controllers\Porter\PorterVisitController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Psychology\PsychologyCoordinationController;
use App\Http\Controllers\PublicSiteContentMediaController;
use App\Http\Controllers\PublicWebAnalyticsController;
use App\Http\Controllers\RelevantCalendar\CalendarEventAttachmentController;
use App\Http\Controllers\RelevantCalendar\CalendarEventController;
use App\Http\Controllers\RelevantCalendar\CalendarInstitutionController;
use App\Http\Controllers\RelevantCalendar\CalendarProcessTypeController;
use App\Http\Controllers\Remuneration\PayslipModuleController;
use App\Http\Controllers\Remuneration\RemunerationDocumentController;
use App\Http\Controllers\Remuneration\RemunerationModuleController;
use App\Http\Controllers\RiskPrevention\PreventiveProgramController;
use App\Http\Controllers\RiskPrevention\RiskEvidenceController;
use App\Http\Controllers\RiskPrevention\RiskMatrixAuditController;
use App\Http\Controllers\RiskPrevention\RiskMatrixCatalogController;
use App\Http\Controllers\RiskPrevention\RiskMatrixController;
use App\Http\Controllers\RiskPrevention\RiskMatrixDashboardController;
use App\Http\Controllers\RiskPrevention\RiskMatrixExportController;
use App\Http\Controllers\RiskPrevention\RiskMatrixImportController;
use App\Http\Controllers\RiskPrevention\RiskMatrixParticipationController;
use App\Http\Controllers\RiskPrevention\RiskMatrixVersionController;
use App\Http\Controllers\RiskPrevention\RiskMatrixWorkflowController;
use App\Http\Controllers\RiskPrevention\RiskPreventionAccidentController;
use App\Http\Controllers\RiskPrevention\RiskPreventionCatalogController;
use App\Http\Controllers\RiskPrevention\RiskPreventionDashboardController;
use App\Http\Controllers\RiskPrevention\RiskPreventionDocumentController;
use App\Http\Controllers\RiskPrevention\RiskPreventionEmergencyController;
use App\Http\Controllers\RiskPrevention\RiskPreventionEppController;
use App\Http\Controllers\RiskPrevention\RiskPreventionFireExtinguisherController;
use App\Http\Controllers\RiskPrevention\RiskPreventionJointCommitteeController;
use App\Http\Controllers\RiskPrevention\RiskPreventionPersonnelController;
use App\Http\Controllers\RiskPrevention\RiskPreventionReportController;
use App\Http\Controllers\RiskPrevention\RiskPreventionTrainingController;
use App\Http\Controllers\RiskPrevention\VepRiskCalculatorController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\Schedule\ScheduleCatalogController;
use App\Http\Controllers\Schedule\ScheduleConfigController;
use App\Http\Controllers\Schedule\ScheduleEventController;
use App\Http\Controllers\Schedule\ScheduleSubjectController;
use App\Http\Controllers\Schedule\ScheduleSummaryController;
use App\Http\Controllers\Schedule\SchoolDayTemplateController;
use App\Http\Controllers\Schedule\StudyPlanController;
use App\Http\Controllers\Schedule\TeacherContractController;
use App\Http\Controllers\Schedule\TeacherScheduleLayerController;
use App\Http\Controllers\Security\SecurityCatalogController;
use App\Http\Controllers\Security\SecurityDashboardController;
use App\Http\Controllers\Security\SecurityIncidentController;
use App\Http\Controllers\Security\SecurityNotificationController;
use App\Http\Controllers\Security\SecurityShiftController;
use App\Http\Controllers\SiteEventController;
use App\Http\Controllers\SiteInstallationController;
use App\Http\Controllers\SiteOrganizationController;
use App\Http\Controllers\Spaces\DependencyReservationController;
use App\Http\Controllers\Spaces\DependencyTypeController;
use App\Http\Controllers\Spaces\SpaceStatisticsController;
use App\Http\Controllers\Staff\DepartmentController;
use App\Http\Controllers\Staff\Permissions\PermissionDashboardController;
use App\Http\Controllers\Staff\Permissions\PermissionReportController;
use App\Http\Controllers\Staff\Permissions\PermissionRequestController;
use App\Http\Controllers\Staff\Permissions\PermissionRequestDocumentController;
use App\Http\Controllers\Staff\Permissions\PermissionRequestReplacementController;
use App\Http\Controllers\Staff\Permissions\PermissionTypeController;
use App\Http\Controllers\Staff\Permissions\PermissionTypeWatcherController;
use App\Http\Controllers\Staff\StaffController;
use App\Http\Controllers\Staff\StaffDocumentController;
use App\Http\Controllers\Staff\StaffPermissionWatcherController;
use App\Http\Controllers\StudentHealth\StudentMedicalLeaveController;
use App\Http\Controllers\StudentLifePostController;
use App\Http\Controllers\Students\AcademicYearController;
use App\Http\Controllers\Students\CourseSectionController;
use App\Http\Controllers\Students\EducationLevelController;
use App\Http\Controllers\Students\StudentController;
use App\Http\Controllers\Students\StudentEnrollmentController;
use App\Http\Controllers\Students\StudentEnrollmentManagementController;
use App\Http\Controllers\Students\StudentPromotionController;
use App\Http\Controllers\Students\StudentReportController;
use App\Http\Controllers\Supply\SupplyDeliveryController;
use App\Http\Controllers\Supply\SupplyItemController;
use App\Http\Controllers\Supply\SupplyReceiptController;
use App\Http\Controllers\Supply\SupplyRequestController;
use App\Http\Controllers\Supply\SupplyStoreroomController;
use App\Http\Controllers\SystemModuleController;
use App\Http\Controllers\Tasks\TaskAssignerController;
use App\Http\Controllers\Tasks\TaskController;
use App\Http\Controllers\Tasks\TaskReportController;
use App\Http\Controllers\TestimonialController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WebAnalyticsDashboardController;
use App\Http\Middleware\NoStoreSensitiveResponse;
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

require __DIR__.'/social_work.php';

require __DIR__.'/psychology.php';

require __DIR__.'/libro_digital.php';

require __DIR__.'/pedagogical_management.php';

Route::post('/login', [APIController::class, 'login']);
Route::post('/forget-password', [APIController::class, 'forget_pass']);
Route::post('/reset-password', [APIController::class, 'reset_pass']);
Route::prefix('public/web-analytics')->middleware('throttle:public-web-analytics')->group(function () {
    Route::post('/page-view', [PublicWebAnalyticsController::class, 'store']);
    Route::post('/engagement', [PublicWebAnalyticsController::class, 'engagement']);
});
Route::prefix('convivencia/public')->middleware('convivencia.installed')->group(function () {
    Route::post('/complaints', [ConvivenciaPublicComplaintController::class, 'store']);
    Route::get('/complaints/{folio}', [ConvivenciaPublicComplaintController::class, 'show']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/internal-notifications', [InternalNotificationController::class, 'index']);
    Route::put('/internal-notifications/read-all', [InternalNotificationController::class, 'markAllAsRead']);
    Route::put('/internal-notifications/{notification}/read', [InternalNotificationController::class, 'markAsRead']);
    Route::get('/psychology-coordinations/mine', [PsychologyCoordinationController::class, 'mine']);
    Route::patch('/psychology-coordinations/{coordination}/respond', [PsychologyCoordinationController::class, 'respond'])->middleware('throttle:30,1');

    Route::get('/me/modules', [MeController::class, 'modules']);
    Route::get('/me/permissions', [MeController::class, 'permissions']);
    Route::get('/me/profile', [ProfileController::class, 'show']);
    Route::post('/me/profile', [ProfileController::class, 'update']);
    Route::put('/me/password', [ProfileController::class, 'updatePassword']);
    Route::get('/inicio/overview', HomeDashboardController::class);
    Route::post('/logout', [APIController::class, 'logout']);
    Route::get('/logout', [APIController::class, 'logout']);

    Route::prefix('documentation')->group(function () {
        Route::get('/catalogs', [ManagedDocumentController::class, 'catalogs'])
            ->middleware('permission:documentation.view');
        Route::get('/', [ManagedDocumentController::class, 'index'])
            ->middleware('permission:documentation.view');
        Route::post('/', [ManagedDocumentController::class, 'store'])
            ->middleware('permission:documentation.create');
        Route::get('/{managedDocument}/download', [ManagedDocumentController::class, 'download'])
            ->whereNumber('managedDocument')
            ->middleware('permission:documentation.view');
        Route::get('/{managedDocument}', [ManagedDocumentController::class, 'show'])
            ->whereNumber('managedDocument')
            ->middleware('permission:documentation.view');
        Route::match(['put', 'patch', 'post'], '/{managedDocument}', [ManagedDocumentController::class, 'update'])
            ->whereNumber('managedDocument')
            ->middleware('permission:documentation.update');
        Route::delete('/{managedDocument}', [ManagedDocumentController::class, 'destroy'])
            ->whereNumber('managedDocument')
            ->middleware('permission:documentation.delete');
    });

    Route::prefix('student-medical-leaves')->group(function () {
        Route::get('/', [StudentMedicalLeaveController::class, 'index']);
        Route::get('/students', [StudentMedicalLeaveController::class, 'students']);
        Route::get('/{certificate}/attachment', [StudentMedicalLeaveController::class, 'downloadAttachment'])
            ->whereNumber('certificate');
        Route::post('/', [StudentMedicalLeaveController::class, 'store']);
        Route::put('/{certificate}', [StudentMedicalLeaveController::class, 'update'])
            ->whereNumber('certificate');
    });

    Route::get('/deploy/status', [DeployController::class, 'status'])->middleware('superadmin');
    Route::post('/deploy', [DeployController::class, 'run'])->middleware(['superadmin', 'throttle:2,1']);

    Route::prefix('internal-communications')->group(function () {
        Route::post('/{internalAnnouncement}/read', [InternalAnnouncementController::class, 'markRead']);
        Route::get('/catalogs', [InternalAnnouncementController::class, 'catalogs'])->middleware('permission:ver_comunicaciones_internas');
        Route::get('/', [InternalAnnouncementController::class, 'index'])->middleware('permission:ver_comunicaciones_internas');
        Route::post('/', [InternalAnnouncementController::class, 'store'])->middleware('permission:gestionar_comunicaciones_internas');
        Route::get('/{internalAnnouncement}', [InternalAnnouncementController::class, 'show'])->middleware('permission:ver_comunicaciones_internas');
        Route::put('/{internalAnnouncement}', [InternalAnnouncementController::class, 'update'])->middleware('permission:gestionar_comunicaciones_internas');
        Route::delete('/{internalAnnouncement}', [InternalAnnouncementController::class, 'destroy'])->middleware('permission:gestionar_comunicaciones_internas');
    });

    Route::prefix('messaging')->middleware(['messaging.available', 'throttle:messaging'])->group(function () {
        Route::get('/summary', [MessagingController::class, 'summary']);
        Route::get('/config', [MessagingController::class, 'config']);
        Route::get('/users/search', [MessagingController::class, 'users'])->middleware('throttle:messaging-search');
        Route::get('/search', [MessagingController::class, 'search'])->middleware('throttle:messaging-search');

        Route::get('/conversations', [MessagingConversationController::class, 'index']);
        Route::post('/conversations/direct', [MessagingConversationController::class, 'direct'])->middleware('throttle:messaging-conversation-create');
        Route::post('/conversations/group', [MessagingConversationController::class, 'group'])->middleware('throttle:messaging-conversation-create');
        Route::post('/conversations/announcement', [MessagingConversationController::class, 'announcement'])->middleware('throttle:messaging-announcement');
        Route::get('/conversations/{conversation}', [MessagingConversationController::class, 'show']);
        Route::patch('/conversations/{conversation}', [MessagingConversationController::class, 'update']);
        Route::match(['post', 'delete'], '/conversations/{conversation}/{preference}', [MessagingConversationController::class, 'preference'])->whereIn('preference', ['archive', 'pin', 'mute']);
        Route::post('/conversations/{conversation}/lock', [MessagingConversationController::class, 'lock']);
        Route::delete('/conversations/{conversation}/lock', [MessagingConversationController::class, 'lock']);
        Route::get('/conversations/{conversation}/participants', [MessagingConversationController::class, 'participants']);
        Route::post('/conversations/{conversation}/participants', [MessagingConversationController::class, 'addParticipant']);
        Route::delete('/conversations/{conversation}/participants/{user}', [MessagingConversationController::class, 'removeParticipant']);
        Route::patch('/conversations/{conversation}/participants/{user}', [MessagingConversationController::class, 'updateParticipant']);
        Route::post('/conversations/{conversation}/transfer-ownership', [MessagingConversationController::class, 'transferOwnership']);
        Route::post('/conversations/{conversation}/leave', [MessagingConversationController::class, 'leave']);

        Route::get('/conversations/{conversation}/messages', [MessagingMessageController::class, 'index']);
        Route::post('/conversations/{conversation}/messages', [MessagingMessageController::class, 'store'])->middleware('throttle:messaging-send');
        Route::get('/messages/{message}', [MessagingMessageController::class, 'show']);
        Route::patch('/messages/{message}', [MessagingMessageController::class, 'update']);
        Route::delete('/messages/{message}', [MessagingMessageController::class, 'destroy']);
        Route::post('/messages/{message}/supersede', [MessagingMessageController::class, 'supersede']);
        Route::post('/messages/{message}/reactions', [MessagingMessageController::class, 'react'])->middleware('throttle:messaging-send');
        Route::delete('/messages/{message}/reactions/{reaction}', [MessagingMessageController::class, 'unreact']);

        Route::post('/receipts/delivered', [MessagingReceiptController::class, 'delivered']);
        Route::post('/conversations/{conversation}/read', [MessagingReceiptController::class, 'read']);
        Route::post('/messages/{message}/acknowledge', [MessagingReceiptController::class, 'acknowledge'])->middleware('throttle:messaging-acknowledge');
        Route::get('/messages/{message}/receipts', [MessagingReceiptController::class, 'index']);
        Route::post('/messages/{message}/reminders', [MessagingReceiptController::class, 'remind'])->middleware('throttle:messaging-reminder');
        Route::post('/messages/{message}/waivers', [MessagingReceiptController::class, 'waive']);
        Route::get('/messages/{message}/receipt-export', [MessagingReceiptController::class, 'export']);

        Route::post('/uploads', [MessagingUploadController::class, 'store'])->middleware('throttle:messaging-upload');
        Route::delete('/uploads/{upload}', [MessagingUploadController::class, 'destroy']);
        Route::get('/attachments/{attachment}', [MessagingUploadController::class, 'download']);
    });

    // Administración (RBAC)
    Route::prefix('admin')->group(function () {
        Route::get('/backups', [BackupController::class, 'index'])->middleware('superadmin');
        Route::get('/backups/{filename}/download', [BackupController::class, 'download'])->middleware('superadmin');

        Route::get('/dashboard', [SuperAdminDashboardController::class, 'index'])->middleware('permission:administrar_modulos');
        Route::get('/dashboard/report', [SuperAdminDashboardController::class, 'report'])->middleware('permission:administrar_modulos');

        Route::get('/users/catalogs', [UserController::class, 'catalogs'])->middleware('permission:administrar_usuarios');
        Route::get('/users', [UserController::class, 'index'])->middleware('permission:administrar_usuarios');
        Route::post('/users', [UserController::class, 'store'])->middleware('permission:administrar_usuarios');
        Route::delete('/users/bulk', [UserController::class, 'bulkDestroy'])->middleware('permission:administrar_usuarios');
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
        Route::delete('/roles/{role}/users/{user}', [RoleController::class, 'removeUser'])->middleware('permission:administrar_roles');
        Route::put('/roles/{role}/permissions', [RoleController::class, 'setPermissions'])->middleware('permission:administrar_roles');
        Route::put('/roles/{role}/modules', [RoleController::class, 'setModules'])->middleware('permission:administrar_roles');
        Route::post('/impersonate/roles/{roleSlug}', [RoleImpersonationController::class, 'switch']);

        Route::get('/permissions', [PermissionController::class, 'index'])->middleware('permission:administrar_permisos');
        Route::post('/permissions', [PermissionController::class, 'store'])->middleware('permission:administrar_permisos');

        Route::get('/modules', [SystemModuleController::class, 'index'])->middleware('permission:administrar_modulos');
        Route::post('/modules', [SystemModuleController::class, 'store'])->middleware('permission:administrar_modulos');
        Route::put('/modules/{systemModule}', [SystemModuleController::class, 'update'])->middleware('permission:administrar_modulos');
        Route::put('/modules/{systemModule}/active', [SystemModuleController::class, 'setActive'])->middleware('permission:administrar_modulos');

        Route::get('/news/catalogs', [NewsPostController::class, 'catalogs'])->middleware('permission:ver_noticias');
        Route::get('/news', [NewsPostController::class, 'index'])->middleware('permission:ver_noticias');
        Route::post('/news', [NewsPostController::class, 'store'])->middleware('permission:gestionar_noticias');
        Route::get('/news/{newsPost}', [NewsPostController::class, 'show'])->middleware('permission:ver_noticias');
        Route::put('/news/{newsPost}', [NewsPostController::class, 'update'])->middleware('permission:gestionar_noticias');
        Route::delete('/news/{newsPost}', [NewsPostController::class, 'destroy'])->middleware('permission:gestionar_noticias');

        Route::get('/events/catalogs', [SiteEventController::class, 'catalogs'])->middleware('permission:ver_eventos');
        Route::get('/events', [SiteEventController::class, 'index'])->middleware('permission:ver_eventos');
        Route::post('/events', [SiteEventController::class, 'store'])->middleware('permission:gestionar_eventos');
        Route::get('/events/{siteEvent}', [SiteEventController::class, 'show'])->middleware('permission:ver_eventos');
        Route::put('/events/{siteEvent}', [SiteEventController::class, 'update'])->middleware('permission:gestionar_eventos');
        Route::delete('/events/{siteEvent}', [SiteEventController::class, 'destroy'])->middleware('permission:gestionar_eventos');

        Route::get('/testimonials/catalogs', [TestimonialController::class, 'catalogs'])->middleware('permission:ver_testimonios');
        Route::get('/testimonials', [TestimonialController::class, 'index'])->middleware('permission:ver_testimonios');
        Route::post('/testimonials', [TestimonialController::class, 'store'])->middleware('permission:gestionar_testimonios');
        Route::get('/testimonials/{testimonial}/image', [PublicSiteContentMediaController::class, 'adminTestimonialImage'])
            ->middleware('permission:ver_testimonios')
            ->name('api.admin.testimonials.image');
        Route::get('/testimonials/{testimonial}', [TestimonialController::class, 'show'])->middleware('permission:ver_testimonios');
        Route::match(['put', 'patch'], '/testimonials/{testimonial}', [TestimonialController::class, 'update'])->middleware('permission:gestionar_testimonios');
        Route::delete('/testimonials/{testimonial}', [TestimonialController::class, 'destroy'])->middleware('permission:gestionar_testimonios');

        Route::get('/student-life/catalogs', [StudentLifePostController::class, 'catalogs'])->middleware('permission:ver_vida_estudiantil');
        Route::get('/student-life', [StudentLifePostController::class, 'index'])->middleware('permission:ver_vida_estudiantil');
        Route::post('/student-life', [StudentLifePostController::class, 'store'])->middleware('permission:gestionar_vida_estudiantil');
        Route::get('/student-life/{studentLifePost}/cover', [PublicSiteContentMediaController::class, 'adminStudentLifeCover'])
            ->middleware('permission:ver_vida_estudiantil')
            ->name('api.admin.student-life.cover');
        Route::get('/student-life/{studentLifePost}/gallery/{studentLifePostImage}', [PublicSiteContentMediaController::class, 'adminStudentLifeGallery'])
            ->middleware('permission:ver_vida_estudiantil')
            ->name('api.admin.student-life.gallery');
        Route::get('/student-life/{studentLifePost}', [StudentLifePostController::class, 'show'])->middleware('permission:ver_vida_estudiantil');
        Route::match(['put', 'patch'], '/student-life/{studentLifePost}', [StudentLifePostController::class, 'update'])->middleware('permission:gestionar_vida_estudiantil');
        Route::delete('/student-life/{studentLifePost}', [StudentLifePostController::class, 'destroy'])->middleware('permission:gestionar_vida_estudiantil');

        Route::get('/installations/catalogs', [SiteInstallationController::class, 'catalogs'])
            ->middleware('permission:ver_instalaciones_sitio');
        Route::get('/installations', [SiteInstallationController::class, 'index'])
            ->middleware('permission:ver_instalaciones_sitio');
        Route::post('/installations', [SiteInstallationController::class, 'store'])
            ->middleware('permission:gestionar_instalaciones_sitio');
        Route::patch('/installations/reorder', [SiteInstallationController::class, 'reorder'])
            ->middleware('permission:gestionar_instalaciones_sitio');
        Route::get('/installations/{siteInstallation}/cover', [PublicSiteContentMediaController::class, 'adminInstallationCover'])
            ->middleware('permission:ver_instalaciones_sitio')
            ->name('api.admin.installations.cover');
        Route::get('/installations/{siteInstallation}/gallery/{siteInstallationImage}', [PublicSiteContentMediaController::class, 'adminInstallationGallery'])
            ->middleware('permission:ver_instalaciones_sitio')
            ->name('api.admin.installations.gallery');
        Route::get('/installations/{siteInstallation}', [SiteInstallationController::class, 'show'])
            ->middleware('permission:ver_instalaciones_sitio');
        Route::match(['put', 'patch'], '/installations/{siteInstallation}', [SiteInstallationController::class, 'update'])
            ->middleware('permission:gestionar_instalaciones_sitio');
        Route::delete('/installations/{siteInstallation}', [SiteInstallationController::class, 'destroy'])
            ->middleware('permission:gestionar_instalaciones_sitio');

        Route::prefix('site-organizations')->group(function () {
            Route::get('/catalogs', [SiteOrganizationController::class, 'catalogs']);
            Route::get('/students', [SiteOrganizationController::class, 'students']);
            Route::get('/staff', [SiteOrganizationController::class, 'staff']);
            Route::post('/roles', [SiteOrganizationController::class, 'storeRole']);
            Route::put('/roles/{siteOrganizationRole}', [SiteOrganizationController::class, 'updateRole']);
            Route::get('/', [SiteOrganizationController::class, 'index']);
            Route::post('/', [SiteOrganizationController::class, 'store']);
            Route::get('/{type}/{id}', [SiteOrganizationController::class, 'show'])
                ->whereNumber('id');
            Route::match(['put', 'patch'], '/{type}/{id}', [SiteOrganizationController::class, 'update'])
                ->whereNumber('id');
            Route::delete('/{type}/{id}', [SiteOrganizationController::class, 'destroy'])
                ->whereNumber('id');
        });

        Route::get('/contact-messages/catalogs', [ContactMessageController::class, 'catalogs'])->middleware('permission:ver_contactos_sitio');
        Route::get('/contact-messages', [ContactMessageController::class, 'index'])->middleware('permission:ver_contactos_sitio');
        Route::get('/contact-messages/{contactMessage}', [ContactMessageController::class, 'show'])->middleware('permission:ver_contactos_sitio');
        Route::put('/contact-messages/{contactMessage}', [ContactMessageController::class, 'update'])->middleware('permission:gestionar_contactos_sitio');
        Route::delete('/contact-messages/{contactMessage}', [ContactMessageController::class, 'destroy'])->middleware('permission:gestionar_contactos_sitio');

        Route::get('/web-analytics', [WebAnalyticsDashboardController::class, 'index'])
            ->middleware('permission:ver_metricas_sitio');

        Route::get('/cargos', [CargoController::class, 'index'])->middleware('permission:administrar_cargos');
        Route::post('/cargos', [CargoController::class, 'store'])->middleware('permission:administrar_cargos');
        Route::put('/cargos/{cargo}', [CargoController::class, 'update'])->middleware('permission:administrar_cargos');
        Route::put('/cargos/{cargo}/active', [CargoController::class, 'setActive'])->middleware('permission:administrar_cargos');

        Route::get('/organigram/catalogs', [OrganigramController::class, 'catalogs'])->middleware('permission:administrar_organigrama');
        Route::get('/organigram', [OrganigramController::class, 'index'])->middleware('permission:administrar_organigrama');
        Route::get('/organigram/{staff}', [OrganigramController::class, 'show'])->middleware('permission:administrar_organigrama');
        Route::put('/organigram/{staff}/relations', [OrganigramController::class, 'sync'])->middleware('permission:administrar_organigrama');
    });

    Route::get('/superadmin/logbooks', [SuperAdminLogbookReviewController::class, 'index'])
        ->middleware('permission:superadmin.logbooks.view');

    Route::prefix('superadmin')->middleware('superadmin')->group(function () {
        Route::get('/reloj-control/users', [RelojControlController::class, 'users'])->middleware('throttle:30,1');
        Route::post('/reloj-control/attendance', [RelojControlController::class, 'attendance'])->middleware('throttle:20,1');
        Route::post('/reloj-control/reports', [RelojControlController::class, 'reports'])->middleware('throttle:10,1');
        Route::get('/usage-level/users', [SuperAdminUsageLevelController::class, 'index']);
        Route::get('/usage-level/users/{user}', [SuperAdminUsageLevelController::class, 'show'])->whereNumber('user');
        Route::get('/supply-requests', [SuperAdminSupplyRequestController::class, 'index']);
        Route::get('/supply-requests/{supplyRequest}', [SuperAdminSupplyRequestController::class, 'show']);
        Route::post('/supply-requests/{supplyRequest}', [SuperAdminSupplyRequestController::class, 'update']);
    });

    Route::prefix('attendance-statistics')->group(function () {
        Route::get('/dashboard', [AttendanceStatisticsController::class, 'dashboard'])->middleware('permission:attendance_statistics.view');
        Route::get('/timeline', [AttendanceStatisticsController::class, 'timeline'])->middleware('permission:attendance_statistics.view');
        Route::get('/courses', [AttendanceStatisticsController::class, 'courses'])->middleware('permission:attendance_statistics.view_course');
        Route::get('/students', [AttendanceStatisticsController::class, 'students'])->middleware('permission:attendance_statistics.view_student');
        Route::get('/students/{studentProfile}', [AttendanceStatisticsController::class, 'student'])->middleware('permission:attendance_statistics.view_student');
        Route::get('/heatmap', [AttendanceStatisticsController::class, 'heatmap'])->middleware('permission:attendance_statistics.view_course');
        Route::get('/risk', [AttendanceStatisticsController::class, 'risk'])->middleware('permission:attendance_statistics.view');
        Route::get('/alerts', [AttendanceStatisticsController::class, 'alerts'])->middleware('permission:attendance_statistics.view');
        Route::post('/alerts/{attendanceAlert}/assign', [AttendanceStatisticsManagementController::class, 'assignAlert'])->middleware('permission:attendance_statistics.manage_alerts');

        Route::get('/interventions', [AttendanceStatisticsManagementController::class, 'interventions'])->middleware('permission:attendance_statistics.view');
        Route::post('/interventions', [AttendanceStatisticsManagementController::class, 'storeIntervention'])->middleware('permission:attendance_statistics.manage_interventions');
        Route::patch('/interventions/{attendanceIntervention}', [AttendanceStatisticsManagementController::class, 'updateIntervention'])->middleware('permission:attendance_statistics.manage_interventions');

        Route::get('/goals', [AttendanceStatisticsManagementController::class, 'goals'])->middleware('permission:attendance_statistics.view');
        Route::post('/goals', [AttendanceStatisticsManagementController::class, 'storeGoal'])->middleware('permission:attendance_statistics.manage_goals');
        Route::put('/goals/{attendanceGoal}', [AttendanceStatisticsManagementController::class, 'updateGoal'])->middleware('permission:attendance_statistics.manage_goals');
        Route::delete('/goals/{attendanceGoal}', [AttendanceStatisticsManagementController::class, 'destroyGoal'])->middleware('permission:attendance_statistics.manage_goals');

        Route::post('/simulate', [AttendanceStatisticsController::class, 'simulate'])->middleware('permission:attendance_statistics.view');
        Route::get('/financial-impact', [AttendanceStatisticsController::class, 'financial'])->middleware('permission:attendance_statistics.view_financial');
        Route::get('/data-quality', [AttendanceStatisticsController::class, 'dataQuality'])->middleware('permission:attendance_statistics.configure');
        Route::get('/audit', [AttendanceStatisticsController::class, 'audit'])->middleware('permission:attendance_statistics.view_audit');

        Route::get('/configuration', [AttendanceStatisticsManagementController::class, 'configuration'])->middleware('permission:attendance_statistics.configure');
        Route::put('/configuration/risk-levels/{attendanceRiskLevel}', [AttendanceStatisticsManagementController::class, 'updateRiskLevel'])->middleware('permission:attendance_statistics.configure');
        Route::put('/configuration/alert-rules/{attendanceAlertRule}', [AttendanceStatisticsManagementController::class, 'updateAlertRule'])->middleware('permission:attendance_statistics.configure');
        Route::post('/configuration/financial-parameters', [AttendanceStatisticsManagementController::class, 'storeFinancialParameter'])->middleware('permission:attendance_statistics.configure');
        Route::get('/saved-filters', [AttendanceStatisticsManagementController::class, 'savedFilters'])->middleware('permission:attendance_statistics.view');
        Route::post('/saved-filters', [AttendanceStatisticsManagementController::class, 'storeSavedFilter'])->middleware('permission:attendance_statistics.view');
        Route::put('/preferences', [AttendanceStatisticsManagementController::class, 'updatePreferences'])->middleware('permission:attendance_statistics.view');

        Route::get('/scheduled-reports', [AttendanceStatisticsManagementController::class, 'scheduledReports'])->middleware('permission:attendance_statistics.manage_reports');
        Route::post('/scheduled-reports', [AttendanceStatisticsManagementController::class, 'storeScheduledReport'])->middleware('permission:attendance_statistics.manage_reports');
        Route::delete('/scheduled-reports/{attendanceScheduledReport}', [AttendanceStatisticsManagementController::class, 'destroyScheduledReport'])->middleware('permission:attendance_statistics.manage_reports');

        Route::get('/exports', [AttendanceStatisticsExportController::class, 'index']);
        Route::post('/exports', [AttendanceStatisticsExportController::class, 'store']);
        Route::get('/exports/{attendanceExportJob}', [AttendanceStatisticsExportController::class, 'show']);
        Route::get('/exports/{attendanceExportJob}/download', [AttendanceStatisticsExportController::class, 'download']);
    });

    Route::prefix('attendance-management')->group(function () {
        Route::get('/dashboard', [AttendanceManagementDashboardController::class, 'dashboard']);
        Route::get('/students', [AttendanceManagementDashboardController::class, 'students']);
        Route::get('/students/{studentProfile}', [AttendanceManagementDashboardController::class, 'student']);

        Route::get('/cases', [AttendanceManagementCaseController::class, 'index']);
        Route::post('/cases', [AttendanceManagementCaseController::class, 'store']);
        Route::get('/cases/{attendanceCase}', [AttendanceManagementCaseController::class, 'show']);
        Route::patch('/cases/{attendanceCase}', [AttendanceManagementCaseController::class, 'update']);
        Route::post('/cases/{attendanceCase}/causes', [AttendanceManagementCaseController::class, 'storeCause']);
        Route::post('/cases/{attendanceCase}/family-contacts', [AttendanceManagementCaseController::class, 'storeFamilyContact']);
        Route::post('/cases/{attendanceCase}/interventions', [AttendanceManagementCaseController::class, 'storeIntervention']);
        Route::post('/cases/{attendanceCase}/action-plans', [AttendanceManagementCaseController::class, 'storePlan']);
        Route::post('/cases/{attendanceCase}/agreements', [AttendanceManagementCaseController::class, 'storeAgreement']);
        Route::post('/cases/{attendanceCase}/notes', [AttendanceManagementCaseController::class, 'storeNote']);
        Route::post('/action-plans/{attendanceActionPlan}/evaluate', [AttendanceManagementCaseController::class, 'evaluatePlan']);
        Route::patch('/action-plan-actions/{attendanceActionPlanAction}', [AttendanceManagementCaseController::class, 'updatePlanAction']);

        Route::get('/configuration', [AttendanceManagementConfigurationController::class, 'index']);
        Route::put('/configuration/settings', [AttendanceManagementConfigurationController::class, 'updateSettings']);
        Route::post('/configuration/causes', [AttendanceManagementConfigurationController::class, 'storeCause']);
        Route::put('/configuration/causes/{attendanceAbsenceReason}', [AttendanceManagementConfigurationController::class, 'updateCause']);
        Route::post('/configuration/intervention-types', [AttendanceManagementConfigurationController::class, 'storeInterventionType']);
        Route::put('/configuration/intervention-types/{attendanceInterventionType}', [AttendanceManagementConfigurationController::class, 'updateInterventionType']);
    });

    Route::prefix('students')->group(function () {
        Route::get('/catalogs', [StudentController::class, 'catalogs'])->middleware('permission:ver_estudiantes');
        Route::get('/export', [StudentController::class, 'export'])->middleware('permission:ver_estudiantes');
        Route::post('/import-pdf/chunk', [StudentController::class, 'importPdfChunk'])->middleware('permission:crear_estudiantes');
        Route::post('/import-pdf', [StudentController::class, 'importPdf'])->middleware('permission:crear_estudiantes');
        Route::get('/reports', StudentReportController::class)->middleware('permission:ver_estudiantes');
        Route::get('/reports/details', [StudentReportController::class, 'details'])->middleware('permission:ver_estudiantes');
        Route::get('/reports/missing-data', [StudentReportController::class, 'missingData'])->middleware('permission:ver_estudiantes');

        Route::get('/attendance/dashboard', [AttendanceController::class, 'dashboard'])->middleware('permission:ver_asistencia');
        Route::get('/attendance/alerts', [AttendanceController::class, 'alerts'])->middleware('permission:ver_asistencia');
        Route::get('/attendance/students', [AttendanceController::class, 'students'])->middleware('permission:ver_asistencia');
        Route::post('/attendance/imports/preview', [AttendanceController::class, 'preview'])->middleware('permission:importar_asistencia');
        Route::post('/attendance/imports/{attendanceImport}/confirm', [AttendanceController::class, 'confirm'])->middleware('permission:importar_asistencia');
        Route::get('/attendance/monthly-imports', [MonthlyAttendanceImportController::class, 'index'])->middleware('permission:importar_asistencia');
        Route::post('/attendance/monthly-imports', [MonthlyAttendanceImportController::class, 'store'])->middleware('permission:importar_asistencia');
        Route::get('/attendance/monthly-imports/candidates', [MonthlyAttendanceImportController::class, 'candidates'])->middleware('permission:importar_asistencia');
        Route::patch('/attendance/monthly-import-rows/{monthlyAttendanceImportRow}/match', [MonthlyAttendanceImportController::class, 'resolve'])->middleware('permission:importar_asistencia');
        Route::get('/grades/annual-imports', [AnnualGradeImportController::class, 'index'])->middleware('permission:importar_calificaciones');
        Route::get('/grades/statistics', GradeStatisticsController::class)->middleware('permission:grade_statistics.view');
        Route::get('/grades/statistics/students', GradeStudentStatisticsController::class)->middleware('permission:grade_statistics.view_students');
        Route::get('/grades/statistics/students/{studentProfile}', [GradeStudentStatisticsController::class, 'show'])->middleware('permission:grade_statistics.view_students');
        Route::post('/grades/annual-imports', [AnnualGradeImportController::class, 'store'])->middleware('permission:importar_calificaciones');
        Route::post('/grades/annual-imports/{annualGradeImport}/retry', [AnnualGradeImportController::class, 'retry'])->middleware('permission:importar_calificaciones');
        Route::get('/grades/annual-imports/candidates', [AnnualGradeImportController::class, 'candidates'])->middleware('permission:importar_calificaciones');
        Route::patch('/grades/annual-import-rows/{annualGradeImportRow}/match', [AnnualGradeImportController::class, 'resolve'])->middleware('permission:importar_calificaciones');
        Route::get('/attendance/students/{studentProfile}', [AttendanceController::class, 'student'])->middleware('permission:ver_asistencia');
        Route::get('/attendance/days/{schoolDay}', [AttendanceController::class, 'day'])->middleware('permission:ver_asistencia');
        Route::patch('/attendance/school-days/{schoolDay}', [AttendanceController::class, 'updateSchoolDay'])->middleware('permission:editar_asistencia');
        Route::patch('/attendance/records/{attendanceRecord}', [AttendanceController::class, 'updateRecord'])->middleware('permission:editar_asistencia');
        Route::patch('/attendance/alerts/{attendanceAlert}', [AttendanceController::class, 'updateAlert'])->middleware('permission:gestionar_alertas_asistencia');
        Route::post('/attendance/alerts/{attendanceAlert}/followups', [AttendanceController::class, 'followup'])->middleware('permission:gestionar_alertas_asistencia');
        Route::put('/attendance/projection-settings/{academicYear}', [AttendanceController::class, 'updateProjection'])->middleware('permission:proyectar_ingresos_asistencia');

        Route::get('/levels', [EducationLevelController::class, 'index'])->middleware('permission:ver_configuracion_base_estudiantes');
        Route::post('/levels', [EducationLevelController::class, 'store'])->middleware('permission:administrar_cursos_academicos');
        Route::put('/levels/{educationLevel}', [EducationLevelController::class, 'update'])->middleware('permission:administrar_cursos_academicos');
        Route::delete('/levels/{educationLevel}', [EducationLevelController::class, 'destroy'])->middleware('permission:administrar_cursos_academicos');

        Route::get('/academic-years', [AcademicYearController::class, 'index'])->middleware('permission:ver_configuracion_base_estudiantes');
        Route::post('/academic-years', [AcademicYearController::class, 'store'])->middleware('permission:administrar_anos_academicos');
        Route::put('/academic-years/{academicYear}', [AcademicYearController::class, 'update'])->middleware('permission:administrar_anos_academicos');
        Route::put('/academic-years/{academicYear}/activate', [AcademicYearController::class, 'setActive'])->middleware('permission:administrar_anos_academicos');

        Route::get('/courses', [CourseSectionController::class, 'index'])->middleware('permission:ver_configuracion_base_estudiantes');
        Route::post('/courses', [CourseSectionController::class, 'store'])->middleware('permission:administrar_cursos_academicos');
        Route::get('/courses/{courseSection}', [CourseSectionController::class, 'show'])->middleware('permission:ver_configuracion_base_estudiantes');
        Route::put('/courses/{courseSection}', [CourseSectionController::class, 'update'])->middleware('permission:administrar_cursos_academicos');
        Route::delete('/courses/{courseSection}', [CourseSectionController::class, 'destroy'])->middleware('permission:administrar_cursos_academicos');

        Route::get('/enrollment-management', [StudentEnrollmentManagementController::class, 'index'])->middleware('permission:gestionar_matriculas_estudiantes');
        Route::post('/enrollment-management/{studentEnrollment}/transfer', [StudentEnrollmentManagementController::class, 'transfer'])->middleware('permission:gestionar_matriculas_estudiantes');
        Route::post('/enrollment-management/{studentEnrollment}/withdraw', [StudentEnrollmentManagementController::class, 'withdraw'])->middleware('permission:gestionar_matriculas_estudiantes');
        Route::post('/enrollment-management/{studentEnrollment}/reenter', [StudentEnrollmentManagementController::class, 'reenter'])->middleware('permission:gestionar_matriculas_estudiantes');

        Route::post('/promotions', [StudentPromotionController::class, 'store'])->middleware('permission:promover_estudiantes');

        Route::get('/', [StudentController::class, 'index'])->middleware('permission:ver_estudiantes');
        Route::post('/', [StudentController::class, 'store'])->middleware('permission:crear_estudiantes');
        Route::get('/{studentProfile}/deletion-impact', [StudentController::class, 'deletionImpact'])->middleware('permission:eliminar_estudiantes');
        Route::delete('/{studentProfile}', [StudentController::class, 'destroy'])->middleware('permission:eliminar_estudiantes');
        Route::post('/{studentProfile}/account', [StudentController::class, 'restoreAccount'])->middleware('permission:editar_estudiantes');
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
        Route::post('/key-groups', [PorterKeyController::class, 'storeGroup'])->middleware('permission:gestionar_llaves_porteria');
        Route::post('/keys', [PorterKeyController::class, 'store'])->middleware('permission:gestionar_llaves_porteria');
        Route::post('/keys/{porterKey}/loans', [PorterKeyController::class, 'loan'])->middleware('permission:gestionar_llaves_porteria');
        Route::post('/key-loans/{porterKeyLoan}/return', [PorterKeyController::class, 'returnLoan'])->middleware('permission:gestionar_llaves_porteria');

        Route::get('/reports', PorterReportController::class)->middleware('permission:ver_historial_porteria');
    });

    Route::prefix('tasks')->group(function () {
        Route::get('/catalogs', [TaskController::class, 'catalogs'])->middleware('permission:ver_tareas');
        Route::get('/stats', [TaskController::class, 'stats'])->middleware('permission:ver_tareas');
        Route::get('/reports/catalogs', [TaskReportController::class, 'catalogs'])->middleware('permission:ver_reportes_tareas');
        Route::get('/reports/stats', [TaskReportController::class, 'stats'])->middleware('permission:ver_reportes_tareas');
        Route::get('/reports', [TaskReportController::class, 'index'])->middleware('permission:ver_reportes_tareas');
        Route::get('/all/catalogs', [TaskReportController::class, 'catalogs'])->middleware('superadmin');
        Route::get('/all/stats', [TaskReportController::class, 'stats'])->middleware('superadmin');
        Route::get('/all', [TaskReportController::class, 'index'])->middleware('superadmin');
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
        Route::put('/school-insurance-sequence', [InfirmaryCatalogController::class, 'updateSchoolInsuranceSequence'])->middleware('permission:administrar_catalogos_enfermeria');
        Route::get('/students', [InfirmaryCatalogController::class, 'students'])->middleware('permission:ver_enfermeria');
        Route::get('/students/{studentProfile}/context', [InfirmaryCatalogController::class, 'studentContext'])->middleware('permission:ver_enfermeria');
        Route::get('/dashboard', InfirmaryDashboardController::class)->middleware('permission:ver_enfermeria');

        Route::get('/daily-log/catalogs', [InfirmaryDailyLogController::class, 'catalogs'])->middleware('permission:ver_bitacora_enfermeria');
        Route::get('/daily-log/students', [InfirmaryDailyLogController::class, 'students'])->middleware('permission:ver_bitacora_enfermeria');
        Route::get('/daily-log', [InfirmaryDailyLogController::class, 'index'])->middleware('permission:ver_bitacora_enfermeria');
        Route::post('/daily-log', [InfirmaryDailyLogController::class, 'store'])->middleware('permission:registrar_bitacora_enfermeria');
        Route::put('/daily-log/{dailyLog}', [InfirmaryDailyLogController::class, 'update'])->middleware('permission:registrar_bitacora_enfermeria');

        Route::get('/categories', [InfirmaryAttentionCategoryController::class, 'index'])->middleware('permission:ver_enfermeria');
        Route::post('/categories', [InfirmaryAttentionCategoryController::class, 'store'])->middleware('permission:administrar_catalogos_enfermeria');
        Route::put('/categories/{category}', [InfirmaryAttentionCategoryController::class, 'update'])->middleware('permission:administrar_catalogos_enfermeria');
        Route::delete('/categories/{category}', [InfirmaryAttentionCategoryController::class, 'destroy'])->middleware('permission:administrar_catalogos_enfermeria');

        Route::get('/attentions', [InfirmaryAttentionController::class, 'index'])->middleware('permission:ver_enfermeria');
        Route::post('/attentions', [InfirmaryAttentionController::class, 'store'])->middleware('permission:crear_atenciones_enfermeria');
        Route::get('/attentions/{attention}', [InfirmaryAttentionController::class, 'show'])->middleware('permission:ver_enfermeria');
        Route::put('/attentions/{attention}', [InfirmaryAttentionController::class, 'update'])->middleware('permission:editar_atenciones_enfermeria');
        Route::delete('/attentions/{attention}', [InfirmaryAttentionController::class, 'destroy'])->middleware('permission:eliminar_atenciones_enfermeria');
        Route::post('/attentions/{attention}/finalize', [InfirmaryAttentionController::class, 'finalize'])->middleware('permission:editar_atenciones_enfermeria');
        Route::post('/attentions/{attention}/documents', [InfirmaryDocumentController::class, 'storeForAttention'])->middleware('permission:editar_atenciones_enfermeria');

        Route::get('/staff-attentions', [InfirmaryStaffAttentionController::class, 'index'])->middleware('permission:ver_enfermeria');
        Route::post('/staff-attentions', [InfirmaryStaffAttentionController::class, 'store'])->middleware('permission:crear_atenciones_enfermeria');
        Route::get('/staff-attentions/{attention}', [InfirmaryStaffAttentionController::class, 'show'])->middleware('permission:ver_enfermeria');
        Route::put('/staff-attentions/{attention}', [InfirmaryStaffAttentionController::class, 'update'])->middleware('permission:editar_atenciones_enfermeria');
        Route::delete('/staff-attentions/{attention}', [InfirmaryStaffAttentionController::class, 'destroy'])->middleware('permission:eliminar_atenciones_enfermeria');
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

    Route::prefix('convivencia')->middleware([
        'convivencia.installed',
        'convivencia.access',
        NoStoreSensitiveResponse::class,
    ])->group(function () {
        Route::get('/catalogs', [ConvivenciaCatalogController::class, 'catalogs']);
        Route::get('/students', [ConvivenciaCatalogController::class, 'students']);
        Route::get('/references/{type}', ConvivenciaReferenceController::class);
        Route::get('/dashboard', ConvivenciaDashboardController::class);
        Route::post('/catalog-items', [ConvivenciaCatalogController::class, 'storeCatalogItem']);
        Route::put('/catalog-items/{catalogItem}', [ConvivenciaCatalogController::class, 'updateCatalogItem']);
        Route::post('/external-institutions', [ConvivenciaCatalogController::class, 'storeInstitution']);
        Route::put('/external-institutions/{externalInstitution}', [ConvivenciaCatalogController::class, 'updateInstitution']);

        Route::get('/cases', [ConvivenciaCaseController::class, 'index']);
        Route::post('/cases', [ConvivenciaCaseController::class, 'store']);
        Route::get('/cases/{case}/export-data', [ConvivenciaCaseController::class, 'exportData']);
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
        Route::get('/annual-plans/workspace', ConvivenciaAnnualPlanController::class);
        Route::post('/plans', [ConvivenciaPlanController::class, 'store']);
        Route::get('/plans/{plan}/export-data', [ConvivenciaPlanController::class, 'exportData']);
        Route::get('/plans/{plan}', [ConvivenciaPlanController::class, 'show']);
        Route::put('/plans/{plan}', [ConvivenciaPlanController::class, 'update']);
        Route::delete('/plans/{plan}', [ConvivenciaPlanController::class, 'destroy']);
        Route::post('/plans/{plan}/attachments', [ConvivenciaAttachmentController::class, 'storeForPlan']);
        Route::get('/plans/{plan}/calendar', ConvivenciaPlanCalendarController::class);
        Route::post('/plans/{plan}/actions', [ConvivenciaPlanActionController::class, 'store']);
        Route::post('/plans/{plan}/clone-to-year', [ConvivenciaAnnualPlanController::class, 'cloneToYear']);
        Route::post('/plans/{plan}/versions/{version}/restore', [ConvivenciaPlanVersionController::class, 'restore']);
        Route::get('/plan-actions/{action}', [ConvivenciaPlanActionController::class, 'show']);
        Route::put('/plan-actions/{action}', [ConvivenciaPlanActionController::class, 'update']);
        Route::delete('/plan-actions/{action}', [ConvivenciaPlanActionController::class, 'destroy']);
        Route::post('/plan-actions/{action}/activities', [ConvivenciaPlanActivityController::class, 'store']);
        Route::put('/plan-activities/{activity}', [ConvivenciaPlanActivityController::class, 'update']);
        Route::delete('/plan-activities/{activity}', [ConvivenciaPlanActivityController::class, 'destroy']);
        Route::post('/plan-activities/{activity}/attachments', [ConvivenciaAttachmentController::class, 'storeForPlanActivity']);

        Route::get('/derivations', [ConvivenciaDerivationController::class, 'index']);
        Route::post('/derivations', [ConvivenciaDerivationController::class, 'store']);
        Route::get('/derivations/{derivation}', [ConvivenciaDerivationController::class, 'show']);
        Route::put('/derivations/{derivation}', [ConvivenciaDerivationController::class, 'update']);
        Route::delete('/derivations/{derivation}', [ConvivenciaDerivationController::class, 'destroy']);
        Route::post('/derivations/{derivation}/convert-to-case', [ConvivenciaDerivationController::class, 'convertToCase']);
        Route::post('/derivations/{derivation}/attachments', [ConvivenciaAttachmentController::class, 'storeForDerivation']);

        Route::get('/protocols', [ConvivenciaProtocolController::class, 'index']);
        Route::post('/protocols', [ConvivenciaProtocolController::class, 'store']);
        Route::get('/protocols/{protocol}', [ConvivenciaProtocolController::class, 'show']);
        Route::put('/protocols/{protocol}', [ConvivenciaProtocolController::class, 'update']);
        Route::delete('/protocols/{protocol}', [ConvivenciaProtocolController::class, 'destroy']);
        Route::get('/protocol-parts', [ConvivenciaProtocolPartController::class, 'index']);
        Route::post('/protocol-parts', [ConvivenciaProtocolPartController::class, 'store']);
        Route::get('/protocol-parts/{protocolPart}', [ConvivenciaProtocolPartController::class, 'show']);
        Route::put('/protocol-parts/{protocolPart}', [ConvivenciaProtocolPartController::class, 'update']);
        Route::delete('/protocol-parts/{protocolPart}', [ConvivenciaProtocolPartController::class, 'destroy']);
        Route::get('/protocol-activations', [ConvivenciaProtocolController::class, 'activations']);
        Route::post('/protocol-activations', [ConvivenciaProtocolController::class, 'activate']);
        Route::get('/protocol-activations/{activation}', [ConvivenciaProtocolController::class, 'showActivation']);
        Route::get('/protocol-activations/{activation}/progress', [ConvivenciaProtocolRuntimeController::class, 'progress']);
        Route::post('/protocol-activations/{activation}/materialize-runtime', [ConvivenciaProtocolRuntimeController::class, 'materialize']);
        Route::put('/protocol-activations/{activation}', [ConvivenciaProtocolController::class, 'updateActivation']);
        Route::put('/protocol-activation-steps/{step}', [ConvivenciaProtocolRuntimeController::class, 'updateStep']);
        Route::post('/protocol-activation-steps/{step}/complete', [ConvivenciaProtocolRuntimeController::class, 'completeStep']);
        Route::put('/protocol-activation-parts/{part}', [ConvivenciaProtocolRuntimeController::class, 'updatePart']);
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

        Route::get('/reports/course/export-data', [ConvivenciaReportController::class, 'exportData']);
        Route::get('/reports/course', ConvivenciaReportController::class);

        Route::get('/attachments/{attachment}/download', [ConvivenciaAttachmentController::class, 'download']);
        Route::delete('/attachments/{attachment}', [ConvivenciaAttachmentController::class, 'destroy']);
    });

    Route::prefix('orientation')->middleware('permission:orientation.view')->group(function () {
        Route::get('/plans', [OrientationPlanController::class, 'index']);
        Route::get('/calendarization', [OrientationCalendarizationController::class, 'index']);
        Route::post('/plans', [OrientationPlanController::class, 'store'])->middleware('permission:orientation.manage_plan');
        Route::put('/plans/{plan}', [OrientationPlanController::class, 'update'])->middleware('permission:orientation.manage_plan');
        Route::get('/plans/{plan}/calendar', OrientationCalendarController::class);
        Route::get('/plans/{plan}/statistics', OrientationStatisticsController::class);
        Route::post('/plans/{plan}/calendarization/import-reference', [OrientationCalendarizationController::class, 'importReference'])->middleware('permission:orientation.manage_plan');
        Route::post('/plans/{plan}/calendarization', [OrientationCalendarizationController::class, 'store'])->middleware('permission:orientation.manage_plan');
        Route::put('/calendarization/{calendarizationEntry}', [OrientationCalendarizationController::class, 'update'])->middleware('permission:orientation.manage_plan');
        Route::delete('/calendarization/{calendarizationEntry}', [OrientationCalendarizationController::class, 'destroy'])->middleware('permission:orientation.manage_plan');

        Route::post('/plans/{plan}/actions', [OrientationActionController::class, 'store'])->middleware('permission:orientation.manage_plan');
        Route::get('/actions/{action}', [OrientationActionController::class, 'show']);
        Route::put('/actions/{action}', [OrientationActionController::class, 'update'])->middleware('permission:orientation.manage_plan');
        Route::delete('/actions/{action}', [OrientationActionController::class, 'destroy'])->middleware('permission:orientation.manage_plan');

        Route::post('/plans/{plan}/related-plans', [OrientationRelatedPlanController::class, 'store'])->middleware('permission:orientation.manage_plan');
        Route::put('/related-plans/{relatedPlan}', [OrientationRelatedPlanController::class, 'update'])->middleware('permission:orientation.manage_plan');

        Route::post('/actions/{action}/activities', [OrientationActivityController::class, 'store'])->middleware('permission:orientation.manage_execution');
        Route::put('/activities/{activity}', [OrientationActivityController::class, 'update'])->middleware('permission:orientation.manage_execution');

        Route::post('/actions/{action}/evidences', [OrientationEvidenceController::class, 'store'])->middleware('permission:orientation.manage_evidence');
        Route::get('/evidences/{evidence}/download', [OrientationEvidenceController::class, 'download']);
    });

    Route::prefix('risk-prevention')->middleware('risk_prevention.installed')->group(function () {
        Route::middleware('risk_matrix.installed')->group(function () {
            Route::get('/risk-matrices/dashboard', RiskMatrixDashboardController::class)->middleware('permission:risk-matrix.view');
            Route::get('/risk-matrices/catalogs', [RiskMatrixCatalogController::class, 'index'])->middleware('permission:risk-matrix.view');
            Route::post('/risk-matrices/catalogs/items', [RiskMatrixCatalogController::class, 'storeItem'])->middleware('permission:risk-matrix.manage-catalogs');
            Route::patch('/risk-matrices/catalogs/items/{item}/status', [RiskMatrixCatalogController::class, 'toggleItem'])->middleware('permission:risk-matrix.manage-catalogs');
            Route::post('/risk-matrices/methodologies/versions', [RiskMatrixCatalogController::class, 'createMethodologyVersion'])->middleware('permission:risk-matrix.manage-catalogs');
            Route::post('/risk-matrices/vep/calculate', VepRiskCalculatorController::class)->middleware('permission:risk-matrix.view');

            Route::get('/risk-matrices/imports', [RiskMatrixImportController::class, 'index'])->middleware('permission:risk-matrix.import');
            Route::post('/risk-matrices/imports/preview', [RiskMatrixImportController::class, 'preview'])->middleware('permission:risk-matrix.import');
            Route::get('/risk-matrices/imports/{batch}', [RiskMatrixImportController::class, 'show'])->middleware('permission:risk-matrix.import');
            Route::get('/risk-matrices/imports/{batch}/issues', [RiskMatrixImportController::class, 'issues'])->middleware('permission:risk-matrix.import');
            Route::post('/risk-matrices/imports/{batch}/commit', [RiskMatrixImportController::class, 'commit'])->middleware('permission:risk-matrix.import');
            Route::post('/risk-matrices/imports/{batch}/cancel', [RiskMatrixImportController::class, 'cancel'])->middleware('permission:risk-matrix.import');

            Route::get('/risk-matrices', [RiskMatrixController::class, 'index'])->middleware('permission:risk-matrix.view');
            Route::post('/risk-matrices', [RiskMatrixController::class, 'store'])->middleware('permission:risk-matrix.create');
            Route::get('/risk-matrices/{riskMatrix}', [RiskMatrixController::class, 'show'])->middleware('permission:risk-matrix.view');
            Route::delete('/risk-matrices/{riskMatrix}', [RiskMatrixController::class, 'destroy'])->middleware('permission:risk-matrix.delete-draft');
            Route::get('/risk-matrices/{riskMatrix}/versions', [RiskMatrixVersionController::class, 'index'])->middleware('permission:risk-matrix.view');
            Route::post('/risk-matrices/{riskMatrix}/versions', [RiskMatrixVersionController::class, 'store'])->middleware('permission:risk-matrix.create-version');
            Route::get('/risk-matrices/{riskMatrix}/audit', RiskMatrixAuditController::class)->middleware('permission:risk-matrix.view-audit');

            Route::get('/risk-matrix-versions/{riskMatrixVersion}', [RiskMatrixVersionController::class, 'show'])->middleware('permission:risk-matrix.view');
            Route::patch('/risk-matrix-versions/{riskMatrixVersion}', [RiskMatrixVersionController::class, 'update'])->middleware('permission:risk-matrix.update');
            Route::put('/risk-matrix-versions/{riskMatrixVersion}/structure', [RiskMatrixVersionController::class, 'structure'])->middleware('permission:risk-matrix.update');
            Route::get('/risk-matrix-versions/{riskMatrixVersion}/validation', [RiskMatrixVersionController::class, 'validation'])->middleware('permission:risk-matrix.view');
            Route::get('/risk-matrix-versions/{riskMatrixVersion}/compare/{other}', [RiskMatrixVersionController::class, 'compare'])->middleware('permission:risk-matrix.view');
            Route::post('/risk-matrix-versions/{version}/submit', [RiskMatrixWorkflowController::class, 'submit'])->middleware('permission:risk-matrix.submit');
            Route::post('/risk-matrix-versions/{version}/review', [RiskMatrixWorkflowController::class, 'review'])->middleware('permission:risk-matrix.review');
            Route::post('/risk-matrix-versions/{version}/observe', [RiskMatrixWorkflowController::class, 'observe'])->middleware('permission:risk-matrix.observe');
            Route::post('/risk-matrix-versions/{version}/draft', [RiskMatrixWorkflowController::class, 'draft'])->middleware('permission:risk-matrix.review');
            Route::post('/risk-matrix-versions/{version}/approve', [RiskMatrixWorkflowController::class, 'approve'])->middleware('permission:risk-matrix.approve');
            Route::post('/risk-matrix-versions/{version}/archive', [RiskMatrixWorkflowController::class, 'archive'])->middleware('permission:risk-matrix.archive');
            Route::get('/risk-matrix-versions/{version}/export/xlsx', RiskMatrixExportController::class)->middleware('permission:risk-matrix.export');
            Route::post('/risk-matrix-versions/{version}/evidences', [RiskEvidenceController::class, 'store'])->middleware('permission:risk-control.implement');
            Route::post('/risk-matrix-versions/{version}/participations', [RiskMatrixParticipationController::class, 'store'])->middleware('permission:risk-matrix.view');
            Route::delete('/risk-matrix-participations/{participation}', [RiskMatrixParticipationController::class, 'destroy'])->middleware('permission:risk-matrix.update');
            Route::post('/risk-matrix-versions/{version}/reviews', [RiskMatrixParticipationController::class, 'review'])->middleware('permission:risk-matrix.review');
            Route::get('/risk-evidences/{evidence}/download', [RiskEvidenceController::class, 'download'])->middleware('permission:risk-matrix.view');

            Route::get('/preventive-programs', [PreventiveProgramController::class, 'index'])->middleware('permission:preventive-program.view');
            Route::get('/preventive-programs/{program}', [PreventiveProgramController::class, 'show'])->middleware('permission:preventive-program.view');
            Route::patch('/preventive-program-actions/{action}', [PreventiveProgramController::class, 'updateAction'])->middleware('permission:preventive-program.manage');
            Route::post('/preventive-program-actions/{action}/verify', [PreventiveProgramController::class, 'verifyAction'])->middleware('permission:risk-control.verify');
        });

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

        Route::get('/epp/catalogs', [RiskPreventionEppController::class, 'catalogs']);
        Route::get('/epp/items', [RiskPreventionEppController::class, 'itemsIndex']);
        Route::post('/epp/items', [RiskPreventionEppController::class, 'storeItem'])->middleware('permission:gestionar_prevencion_riesgos');
        Route::post('/epp/items/bulk', [RiskPreventionEppController::class, 'bulkStoreItems'])->middleware('permission:gestionar_prevencion_riesgos');
        Route::put('/epp/items/{eppItem}', [RiskPreventionEppController::class, 'updateItem'])->middleware('permission:gestionar_prevencion_riesgos');
        Route::delete('/epp/items/{eppItem}', [RiskPreventionEppController::class, 'destroyItem'])->middleware('permission:gestionar_prevencion_riesgos');
        Route::get('/epp/delivery-records', [RiskPreventionEppController::class, 'deliveryRecordsIndex']);
        Route::post('/epp/delivery-records', [RiskPreventionEppController::class, 'storeDeliveryRecord'])->middleware('permission:registrar_entregas_epp');
        Route::get('/epp/deliveries', [RiskPreventionEppController::class, 'deliveriesIndex']);
        Route::post('/epp/deliveries', [RiskPreventionEppController::class, 'storeDelivery'])->middleware('permission:gestionar_prevencion_riesgos');
        Route::put('/epp/deliveries/{eppDelivery}', [RiskPreventionEppController::class, 'updateDelivery'])->middleware('permission:gestionar_prevencion_riesgos');
        Route::delete('/epp/deliveries/{eppDelivery}', [RiskPreventionEppController::class, 'destroyDelivery'])->middleware('permission:gestionar_prevencion_riesgos');

        Route::get('/trainings', [RiskPreventionTrainingController::class, 'index'])->middleware('permission:ver_prevencion_riesgos');
        Route::post('/trainings', [RiskPreventionTrainingController::class, 'store'])->middleware('permission:gestionar_prevencion_riesgos');
        Route::put('/trainings/{training}', [RiskPreventionTrainingController::class, 'update'])->middleware('permission:gestionar_prevencion_riesgos');
        Route::delete('/trainings/{training}', [RiskPreventionTrainingController::class, 'destroy'])->middleware('permission:gestionar_prevencion_riesgos');
        Route::get('/trainings/{training}/evidence', [RiskPreventionTrainingController::class, 'downloadEvidence'])->middleware('permission:ver_prevencion_riesgos');

        Route::get('/personnel/matrix', [RiskPreventionPersonnelController::class, 'matrix'])->middleware('permission:ver_prevencion_riesgos');
        Route::get('/personnel/requirement-types', [RiskPreventionPersonnelController::class, 'requirementTypes'])->middleware('permission:ver_prevencion_riesgos');
        Route::post('/personnel/requirement-types', [RiskPreventionPersonnelController::class, 'storeRequirement'])->middleware('permission:gestionar_prevencion_riesgos');
        Route::put('/personnel/requirement-types/{requirement}', [RiskPreventionPersonnelController::class, 'updateRequirement'])->middleware('permission:gestionar_prevencion_riesgos');
        Route::delete('/personnel/requirement-types/{requirement}', [RiskPreventionPersonnelController::class, 'destroyRequirement'])->middleware('permission:gestionar_prevencion_riesgos');
        Route::post('/personnel/staff/{staff}/requirements/{requirement}/compliance', [RiskPreventionPersonnelController::class, 'storeCompliance'])->middleware('permission:gestionar_prevencion_riesgos');
        Route::delete('/personnel/compliances/{compliance}', [RiskPreventionPersonnelController::class, 'destroyCompliance'])->middleware('permission:gestionar_prevencion_riesgos');
        Route::get('/personnel/compliances/{compliance}/download', [RiskPreventionPersonnelController::class, 'downloadCompliance'])->middleware('permission:ver_prevencion_riesgos');
        Route::get('/personnel/staff/{staff}/documents/download', [RiskPreventionPersonnelController::class, 'downloadStaffArchive'])->middleware('permission:exportar_prevencion_riesgos');
        Route::get('/personnel/committees', [RiskPreventionPersonnelController::class, 'committees'])->middleware('permission:ver_prevencion_riesgos');
        Route::post('/personnel/committees', [RiskPreventionPersonnelController::class, 'storeCommittee'])->middleware('permission:gestionar_prevencion_riesgos');
        Route::put('/personnel/committees/{committee}', [RiskPreventionPersonnelController::class, 'updateCommittee'])->middleware('permission:gestionar_prevencion_riesgos');
        Route::delete('/personnel/committees/{committee}', [RiskPreventionPersonnelController::class, 'destroyCommittee'])->middleware('permission:gestionar_prevencion_riesgos');

        Route::get('/joint-committees', [RiskPreventionJointCommitteeController::class, 'index']);
        Route::post('/joint-committees/{committee}/documents', [RiskPreventionJointCommitteeController::class, 'storeDocument'])->middleware('permission:cargar_actas_comite_paritario');
        Route::get('/joint-committees/{committee}/documents/{document}/download', [RiskPreventionJointCommitteeController::class, 'downloadDocument']);

        Route::get('/documents', [RiskPreventionDocumentController::class, 'index'])->middleware('permission:ver_prevencion_riesgos');
        Route::post('/documents', [RiskPreventionDocumentController::class, 'store'])->middleware('permission:gestionar_prevencion_riesgos');
        Route::put('/documents/{document}', [RiskPreventionDocumentController::class, 'update'])->middleware('permission:gestionar_prevencion_riesgos');
        Route::delete('/documents/{document}', [RiskPreventionDocumentController::class, 'destroy'])->middleware('permission:gestionar_prevencion_riesgos');
        Route::get('/documents/{document}/download', [RiskPreventionDocumentController::class, 'download'])->middleware('permission:ver_prevencion_riesgos');
        Route::get('/disseminated-documents', [RiskPreventionDocumentController::class, 'disseminatedIndex'])->middleware('permission:ver_documentos_prevencion_difundibles');
        Route::get('/disseminated-documents/{document}/download', [RiskPreventionDocumentController::class, 'downloadDisseminated'])->middleware('permission:ver_documentos_prevencion_difundibles');
    });

    Route::prefix('biblioteca')->group(function () {
        Route::get('/catalogs', BibliotecaCatalogsController::class)->middleware('permission:ver_modulo_biblioteca');
        Route::get('/search', BibliotecaGlobalSearchController::class)->middleware('permission:ver_modulo_biblioteca');
        Route::get('/dashboard', BibliotecaDashboardController::class)->middleware('permission:ver_modulo_biblioteca');
        Route::get('/open-library', OpenLibraryController::class)->middleware(['permission:ver_modulo_biblioteca', 'throttle:20,1']);

        Route::get('/categorias', [BibliotecaManagementController::class, 'categories'])->middleware('permission:ver_modulo_biblioteca');
        Route::post('/categorias', [BibliotecaManagementController::class, 'storeCategory'])->middleware('permission:gestionar_categorias_biblioteca');
        Route::put('/categorias/{categoria}', [BibliotecaManagementController::class, 'updateCategory'])->middleware('permission:gestionar_categorias_biblioteca');
        Route::delete('/categorias/{categoria}', [BibliotecaManagementController::class, 'destroyCategory'])->middleware('permission:gestionar_categorias_biblioteca');
        Route::post('/subcategorias', [BibliotecaManagementController::class, 'storeSubcategory'])->middleware('permission:gestionar_categorias_biblioteca');
        Route::put('/subcategorias/{subcategoria}', [BibliotecaManagementController::class, 'updateSubcategory'])->middleware('permission:gestionar_categorias_biblioteca');
        Route::delete('/subcategorias/{subcategoria}', [BibliotecaManagementController::class, 'destroySubcategory'])->middleware('permission:gestionar_categorias_biblioteca');
        Route::get('/ubicaciones', [BibliotecaManagementController::class, 'locations'])->middleware('permission:ver_modulo_biblioteca');
        Route::post('/ubicaciones', [BibliotecaManagementController::class, 'storeLocation'])->middleware('permission:gestionar_almacenaje_biblioteca');
        Route::put('/ubicaciones/{ubicacion}', [BibliotecaManagementController::class, 'updateLocation'])->middleware('permission:gestionar_almacenaje_biblioteca');
        Route::delete('/ubicaciones/{ubicacion}', [BibliotecaManagementController::class, 'destroyLocation'])->middleware('permission:gestionar_almacenaje_biblioteca');

        Route::get('/obras', [BibliotecaCatalogController::class, 'index'])->middleware('permission:ver_modulo_biblioteca');
        Route::post('/obras', [BibliotecaCatalogController::class, 'store'])->middleware('permission:crear_libros_biblioteca');
        Route::get('/obras/{obra}/cover/{cover}', [BibliotecaCatalogController::class, 'cover'])
            ->where('cover', '[a-fA-F0-9-]+\.(?:jpe?g|png|webp|heic|heif)')
            ->middleware('permission:ver_modulo_biblioteca');
        Route::get('/obras/{obra}', [BibliotecaCatalogController::class, 'show'])->middleware('permission:ver_modulo_biblioteca');
        Route::put('/obras/{obra}', [BibliotecaCatalogController::class, 'update'])->middleware('permission:editar_libros_biblioteca');
        Route::delete('/obras/{obra}', [BibliotecaCatalogController::class, 'destroy'])->middleware('permission:eliminar_libros_biblioteca');
        Route::post('/materiales', [BibliotecaCatalogController::class, 'storeMaterial'])->middleware('permission:gestionar_materiales_biblioteca');

        Route::get('/ejemplares', [BibliotecaInventoryController::class, 'index'])->middleware('permission:ver_modulo_biblioteca');
        Route::post('/ejemplares', [BibliotecaInventoryController::class, 'store'])->middleware('permission:administrar_inventario_biblioteca');
        Route::get('/ejemplares/{ejemplar}', [BibliotecaInventoryController::class, 'show'])->middleware('permission:ver_modulo_biblioteca');
        Route::put('/ejemplares/{ejemplar}', [BibliotecaInventoryController::class, 'update'])->middleware('permission:administrar_inventario_biblioteca');
        Route::post('/ejemplares/{ejemplar}/photos', [BibliotecaInventoryController::class, 'uploadPhotos'])->middleware('permission:administrar_inventario_biblioteca');
        Route::get('/ejemplares/{ejemplar}/photos/{photo}', [BibliotecaInventoryController::class, 'photo'])
            ->where('photo', '[a-fA-F0-9-]+\.(?:jpe?g|png|webp|heic|heif)')
            ->middleware('permission:ver_modulo_biblioteca');
        Route::post('/ejemplares/{ejemplar}/audit', [BibliotecaInventoryController::class, 'audit'])->middleware('permission:administrar_inventario_biblioteca');
        Route::post('/ejemplares/{ejemplar}/damage', [BibliotecaInventoryController::class, 'markDamage'])->middleware('permission:administrar_inventario_biblioteca');
        Route::post('/ejemplares/{ejemplar}/loss', [BibliotecaInventoryController::class, 'markLoss'])->middleware('permission:administrar_inventario_biblioteca');
        Route::post('/ejemplares/{ejemplar}/deactivate', [BibliotecaInventoryController::class, 'deactivate'])->middleware('permission:administrar_inventario_biblioteca');

        Route::get('/prestamos', [BibliotecaLoanController::class, 'index'])->middleware('permission:ver_modulo_biblioteca');
        Route::post('/prestamos', [BibliotecaLoanController::class, 'store'])->middleware('permission:registrar_prestamos_biblioteca');
        Route::get('/prestamos/{prestamo}', [BibliotecaLoanController::class, 'show'])->middleware('permission:ver_modulo_biblioteca');
        Route::put('/prestamos/{prestamo}', [BibliotecaLoanController::class, 'update'])->middleware('permission:registrar_devoluciones_biblioteca');
        Route::post('/prestamos/{prestamo}/renew', [BibliotecaLoanController::class, 'renew'])->middleware('permission:renovar_prestamos_biblioteca');
        Route::post('/prestamos/{prestamo}/return', [BibliotecaLoanController::class, 'return'])->middleware('permission:registrar_devoluciones_biblioteca');
        Route::post('/prestamos/{prestamo}/cancel', [BibliotecaLoanController::class, 'cancel'])->middleware('permission:gestionar_mora_biblioteca');
        Route::get('/lectores-temporales', [BibliotecaTemporaryBorrowerController::class, 'index'])->middleware('permission:ver_modulo_biblioteca');
        Route::post('/lectores-temporales', [BibliotecaTemporaryBorrowerController::class, 'store'])->middleware('permission:registrar_prestamos_biblioteca');
        Route::put('/lectores-temporales/{lectorTemporal}', [BibliotecaTemporaryBorrowerController::class, 'update'])->middleware('permission:registrar_prestamos_biblioteca');

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

        Route::get('/textos-escolares', [BibliotecaTextbookController::class, 'overview'])->middleware('permission:ver_modulo_biblioteca');
        Route::post('/textos-escolares/recepciones', [BibliotecaTextbookController::class, 'storeReception'])->middleware('permission:gestionar_textos_escolares_biblioteca');
        Route::post('/textos-escolares/ordenes', [BibliotecaTextbookController::class, 'storeOrder'])->middleware('permission:gestionar_textos_escolares_biblioteca');
        Route::get('/textos-escolares/ordenes/{orden}', [BibliotecaTextbookController::class, 'showOrder'])->middleware('permission:ver_modulo_biblioteca');
        Route::post('/textos-escolares/ordenes/{orden}/listado', [BibliotecaTextbookController::class, 'generateRoster'])->middleware('permission:gestionar_textos_escolares_biblioteca');
        Route::put('/textos-escolares/entregas/{entrega}', [BibliotecaTextbookController::class, 'updateDelivery'])->middleware('permission:gestionar_textos_escolares_biblioteca');

        Route::get('/pases', [BibliotecaPassController::class, 'index'])->middleware('permission:ver_modulo_biblioteca');
        Route::post('/pases', [BibliotecaPassController::class, 'store'])->middleware('permission:gestionar_pases_biblioteca');
        Route::put('/pases/{pase}', [BibliotecaPassController::class, 'update'])->middleware('permission:gestionar_pases_biblioteca');
        Route::post('/pases/{pase}/{status}', [BibliotecaPassController::class, 'transition'])->middleware('permission:gestionar_pases_biblioteca');

        Route::get('/reportes', BibliotecaReportController::class)->middleware('permission:ver_estadisticas_biblioteca');
    });

    Route::prefix('inspectoria')->group(function () {
        Route::get('/catalogs', InspectoriaCatalogController::class);

        Route::get('/daily-log', [InspectoriaDailyLogController::class, 'index'])
            ->middleware('permission:ver_bitacora_inspectoria');
        Route::post('/daily-log', [InspectoriaDailyLogController::class, 'store'])
            ->middleware('permission:registrar_bitacora_inspectoria');
        Route::put('/daily-log/{dailyLog}', [InspectoriaDailyLogController::class, 'update'])
            ->middleware('permission:registrar_bitacora_inspectoria');

        Route::middleware('permission:ver_modulo_inspectoria')->group(function () {

            Route::get('/attentions', [InspectoriaAttentionController::class, 'index']);
            Route::post('/attentions', [InspectoriaAttentionController::class, 'store'])->middleware('permission:registrar_atenciones_inspectoria');

            Route::get('/course-assignments', [InspectoriaCourseAssignmentController::class, 'index']);
            Route::post('/course-assignments', [InspectoriaCourseAssignmentController::class, 'store'])->middleware('permission:asignar_cursos_inspectoria');
            Route::post('/course-assignments/bulk', [InspectoriaCourseAssignmentController::class, 'bulkStore'])->middleware('permission:asignar_cursos_inspectoria');
            Route::put('/course-assignments/{assignment}', [InspectoriaCourseAssignmentController::class, 'update'])->middleware('permission:asignar_cursos_inspectoria');
            Route::delete('/course-assignments/{assignment}', [InspectoriaCourseAssignmentController::class, 'destroy'])->middleware('permission:asignar_cursos_inspectoria');

            Route::get('/passes', [InspectoriaPassController::class, 'index']);
            Route::post('/passes', [InspectoriaPassController::class, 'store'])->middleware('permission:gestionar_pases_inspectoria');
            Route::put('/passes/{pass}', [InspectoriaPassController::class, 'update'])->middleware('permission:gestionar_pases_inspectoria');
            Route::post('/passes/{pass}/{status}', [InspectoriaPassController::class, 'transition'])->middleware('permission:gestionar_pases_inspectoria');

            Route::get('/students', [InspectoriaStudentController::class, 'index'])->middleware('permission:ver_fichas_inspectoria');
            Route::get('/students/{student}', [InspectoriaStudentController::class, 'show'])->middleware('permission:ver_fichas_inspectoria');
            Route::put('/students/{student}/profile', [InspectoriaStudentController::class, 'updateProfile'])->middleware('permission:editar_fichas_inspectoria');

            Route::get('/withdrawals', [InspectoriaWithdrawalController::class, 'index'])->middleware('permission:ver_retiros_inspectoria');
            Route::get('/withdrawals/{withdrawal}', [InspectoriaWithdrawalController::class, 'show'])->middleware('permission:ver_retiros_inspectoria');

            Route::get('/statistics/staff-lateness', [InspectoriaStatisticsController::class, 'staffLateness'])
                ->middleware('permission:ver_estadisticas_inspectoria');
        });
    });

    Route::prefix('operational/logbook')->group(function () {
        Route::get('/', [OperationalStaffLogbookController::class, 'index'])
            ->middleware('permission:operational_logbook.view');
        Route::post('/', [OperationalStaffLogbookController::class, 'store'])
            ->middleware('permission:operational_logbook.create');
        Route::put('/{entry}', [OperationalStaffLogbookController::class, 'update'])
            ->middleware('permission:operational_logbook.create');
    });

    Route::prefix('operational/transfers')->group(function () {
        Route::get('/catalogs', [OperationalTransferController::class, 'catalogs']);
        Route::get('/calendar', [OperationalTransferController::class, 'calendar']);
        Route::get('/reports', OperationalTransferReportController::class);
        Route::post('/imports/preview', [OperationalTransferImportController::class, 'preview']);
        Route::post('/imports/commit', [OperationalTransferImportController::class, 'commit']);

        Route::get('/providers', [OperationalTransferProviderController::class, 'index']);
        Route::post('/providers', [OperationalTransferProviderController::class, 'store']);
        Route::put('/providers/{provider}', [OperationalTransferProviderController::class, 'update']);
        Route::delete('/providers/{provider}', [OperationalTransferProviderController::class, 'destroy']);

        Route::get('/', [OperationalTransferController::class, 'index']);
        Route::post('/', [OperationalTransferController::class, 'store']);
        Route::get('/{transfer}', [OperationalTransferController::class, 'show']);
        Route::put('/{transfer}', [OperationalTransferController::class, 'update']);
        Route::post('/{transfer}/submit', [OperationalTransferController::class, 'submit']);
        Route::post('/{transfer}/visor/approve', [OperationalTransferController::class, 'visorApprove']);
        Route::post('/{transfer}/visor/observe', [OperationalTransferController::class, 'visorObserve']);
        Route::post('/{transfer}/visor/reject', [OperationalTransferController::class, 'visorReject']);
        Route::post('/{transfer}/administration/approve', [OperationalTransferController::class, 'administrationApprove']);
        Route::post('/{transfer}/administration/observe', [OperationalTransferController::class, 'administrationObserve']);
        Route::post('/{transfer}/administration/reject', [OperationalTransferController::class, 'administrationReject']);
        Route::put('/{transfer}/operation', [OperationalTransferController::class, 'updateOperation']);
        Route::post('/{transfer}/confirm', [OperationalTransferController::class, 'confirm']);
        Route::post('/{transfer}/execute', [OperationalTransferController::class, 'execute']);
        Route::post('/{transfer}/cancel', [OperationalTransferController::class, 'cancel']);
        Route::get('/{transfer}/pdf', [OperationalTransferController::class, 'pdf']);

        Route::post('/{transfer}/quotes', [OperationalTransferQuoteController::class, 'store']);
        Route::post('/{transfer}/quotes/{quote}/select', [OperationalTransferQuoteController::class, 'select']);
        Route::delete('/{transfer}/quotes/{quote}', [OperationalTransferQuoteController::class, 'destroy']);
        Route::post('/{transfer}/documents', [OperationalTransferDocumentController::class, 'store']);
        Route::get('/documents/{document}/download', [OperationalTransferDocumentController::class, 'download']);
        Route::delete('/documents/{document}', [OperationalTransferDocumentController::class, 'destroy']);
    });

    Route::prefix('human-resources')
        ->middleware(NoStoreSensitiveResponse::class)
        ->group(function () {
            Route::get('/absences/export', [HrAbsenceController::class, 'export']);
            Route::get('/absences/calendar', [HrAbsenceController::class, 'calendar']);
            Route::get('/absences', [HrAbsenceController::class, 'index']);
            Route::post('/absences', [HrAbsenceController::class, 'store']);
            Route::put('/absences/{absence}', [HrAbsenceController::class, 'update']);
            Route::delete('/absences/{absence}', [HrAbsenceController::class, 'destroy']);
            Route::put('/absence-balances/{staff}', [HrAbsenceController::class, 'updateBalance']);

            Route::get('/recruitment', [HrRecruitmentController::class, 'index']);
            Route::post('/recruitment/candidates', [HrRecruitmentController::class, 'storeCandidate']);
            Route::put('/recruitment/candidates/{candidate}', [HrRecruitmentController::class, 'updateCandidate']);
            Route::post('/recruitment/candidates/{candidate}/cv', [HrRecruitmentController::class, 'uploadCv']);
            Route::get('/recruitment/candidates/{candidate}/cv', [HrRecruitmentController::class, 'downloadCv']);
            Route::post('/recruitment/vacancies', [HrRecruitmentController::class, 'storeVacancy']);
            Route::put('/recruitment/vacancies/{vacancy}', [HrRecruitmentController::class, 'updateVacancy']);
            Route::post('/recruitment/applications', [HrRecruitmentController::class, 'storeApplication']);
            Route::put('/recruitment/applications/{application}', [HrRecruitmentController::class, 'updateApplication']);
            Route::post('/recruitment/interviews', [HrRecruitmentController::class, 'storeInterview']);
            Route::put('/recruitment/interviews/{interview}', [HrRecruitmentController::class, 'updateInterview']);
            Route::post('/recruitment/interviews/{interview}/report', [HrRecruitmentController::class, 'uploadReport']);
            Route::get('/recruitment/interviews/{interview}/report', [HrRecruitmentController::class, 'downloadReport']);
            Route::post('/recruitment/job-profiles', [HrRecruitmentController::class, 'storeJobProfile']);
            Route::put('/recruitment/job-profiles/{profile}', [HrRecruitmentController::class, 'updateJobProfile']);

            Route::post('/imports/{kind}/preview', [HrImportController::class, 'preview']);
            Route::post('/imports/{kind}/commit', [HrImportController::class, 'commit']);
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

    Route::prefix('remuneraciones')
        ->middleware([
            'permission:remuneraciones.acceso_confidencial,remuneraciones.ver',
            NoStoreSensitiveResponse::class,
        ])
        ->group(function () {
            Route::prefix('liquidaciones-sueldo')->group(function () {
                Route::get('/catalogs', [PayslipModuleController::class, 'catalogs'])->middleware('permission:remuneraciones.liquidaciones_pdf.ver');
                Route::get('/dashboard', [PayslipModuleController::class, 'dashboard'])->middleware('permission:remuneraciones.liquidaciones_pdf.ver');
                Route::get('/history', [PayslipModuleController::class, 'history'])->middleware('permission:remuneraciones.liquidaciones_pdf.ver');
                Route::get('/matrices/{type}', [PayslipModuleController::class, 'matrix'])->middleware('permission:remuneraciones.liquidaciones_pdf.ver');
                Route::get('/reconciliation', [PayslipModuleController::class, 'reconciliation'])->middleware('permission:remuneraciones.liquidaciones_pdf.ver');
                Route::get('/batches', [PayslipModuleController::class, 'batches'])->middleware('permission:remuneraciones.liquidaciones_pdf.ver');
                Route::post('/batches', [PayslipModuleController::class, 'stage'])->middleware('permission:remuneraciones.liquidaciones_pdf.importar');
                Route::get('/batches/{batch}', [PayslipModuleController::class, 'showBatch'])->middleware('permission:remuneraciones.liquidaciones_pdf.ver');
                Route::post('/batches/{batch}/confirm', [PayslipModuleController::class, 'confirmBatch'])->middleware('permission:remuneraciones.liquidaciones_pdf.importar');
                Route::post('/batches/{batch}/annul', [PayslipModuleController::class, 'annul'])->middleware('permission:remuneraciones.liquidaciones_pdf.anular');
                Route::post('/issues/{issue}/resolve', [PayslipModuleController::class, 'resolveIssue'])->middleware('permission:remuneraciones.liquidaciones_pdf.incidencias');
                Route::post('/files/{file}/reprocess', [PayslipModuleController::class, 'reprocess'])->middleware('permission:remuneraciones.liquidaciones_pdf.reprocesar');
                Route::get('/files/{file}/link', [PayslipModuleController::class, 'fileLink'])->middleware('permission:remuneraciones.liquidaciones_pdf.ver');
                Route::get('/files/{file}/download', [PayslipModuleController::class, 'downloadFile'])->name('remuneration.payslips.files.download')->middleware('permission:remuneraciones.liquidaciones_pdf.ver');
                Route::get('/export/excel', [PayslipModuleController::class, 'exportExcel'])->middleware('permission:remuneraciones.liquidaciones_pdf.exportar');
                Route::get('/export/csv/{type}', [PayslipModuleController::class, 'exportCsv'])->middleware('permission:remuneraciones.liquidaciones_pdf.exportar');
                Route::get('/payment-proposals', [PayslipModuleController::class, 'proposals'])->middleware('permission:remuneraciones.liquidaciones_pdf.ver');
                Route::post('/payment-proposals', [PayslipModuleController::class, 'generateProposal'])->middleware('permission:remuneraciones.liquidaciones_pdf.propuesta_pago');
                Route::post('/payment-proposals/{proposal}/confirm', [PayslipModuleController::class, 'confirmProposal'])->middleware('permission:remuneraciones.liquidaciones_pdf.propuesta_pago');
            });
            Route::get('/catalogs', [RemunerationModuleController::class, 'catalogs'])->middleware('permission:remuneraciones.ver');
            Route::prefix('documents')
                ->middleware('permission:remuneraciones.rrhh.gestionar')
                ->group(function () {
                    Route::get('/staff', [RemunerationDocumentController::class, 'staff']);
                    Route::get('/requirements', [RemunerationDocumentController::class, 'requirements']);
                    Route::post('/requirements', [RemunerationDocumentController::class, 'storeRequirement']);
                    Route::put('/requirements/{requirement}', [RemunerationDocumentController::class, 'updateRequirement']);
                    Route::delete('/requirements/{requirement}', [RemunerationDocumentController::class, 'destroyRequirement']);
                    Route::post('/staff/{staff}/requirements/{requirement}', [RemunerationDocumentController::class, 'saveCompliance']);
                    Route::get('/compliances/{control}/download', [RemunerationDocumentController::class, 'download'])
                        ->name('remuneration.documents.download');
                });
            Route::get('/dashboard', [RemunerationModuleController::class, 'dashboard'])->middleware('permission:remuneraciones.ver');
            Route::get('/book-analytics', [RemunerationModuleController::class, 'bookAnalytics'])->middleware('permission:remuneraciones.reportes.ver');
            Route::get('/book-alert-rules', [RemunerationModuleController::class, 'bookAlertRules'])->middleware('permission:remuneraciones.reportes.ver');
            Route::post('/book-alert-rules', [RemunerationModuleController::class, 'storeBookAlertRule'])->middleware('permission:remuneraciones.reportes.ver');
            Route::patch('/book-concept-settings/{conceptKey}', [RemunerationModuleController::class, 'updateBookConceptSetting'])->middleware('permission:remuneraciones.conceptos.gestionar');
            Route::get('/export', [RemunerationModuleController::class, 'export'])->middleware('permission:remuneraciones.reportes.exportar');
            Route::post('/imports/preview', [RemunerationModuleController::class, 'previewImport'])->middleware('permission:remuneraciones.importar');
            Route::post('/imports', [RemunerationModuleController::class, 'importBook'])->middleware('permission:remuneraciones.importar');
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

    Route::prefix('contabilidad')
        ->middleware([
            'permission:contabilidad.acceso_confidencial,contabilidad.ver',
            NoStoreSensitiveResponse::class,
        ])
        ->group(function () {
            Route::get('/catalogs', [AccountingModuleController::class, 'catalogs'])->middleware('permission:contabilidad.ver');
            Route::get('/dashboard', [AccountingModuleController::class, 'dashboard'])->middleware('permission:contabilidad.ver');
            Route::get('/reportes', [AccountingModuleController::class, 'reports'])->middleware('permission:contabilidad.ver');
            Route::get('/export/{report}', [AccountingModuleController::class, 'export'])->middleware('permission:contabilidad.reportes.exportar');
            Route::get('/ejecucion-presupuestaria', [AccountingBudgetExecutionController::class, 'dashboard'])->middleware('permission:contabilidad.ejecucion_presupuestaria.ver');
            Route::post('/ejecucion-presupuestaria/importar', [AccountingBudgetExecutionController::class, 'import'])->middleware('permission:contabilidad.ejecucion_presupuestaria.importar');
            Route::get('/subvenciones/dashboard', [AccountingSubsidyController::class, 'dashboard'])->middleware('permission:contabilidad.subvenciones.ver');
            Route::get('/subvenciones/{settlement}', [AccountingSubsidyController::class, 'show'])->middleware('permission:contabilidad.subvenciones.ver');
            Route::post('/subvenciones/importar', [AccountingSubsidyController::class, 'import'])->middleware('permission:contabilidad.subvenciones.importar');
            Route::post('/subvenciones/manual', [AccountingSubsidyController::class, 'manual'])->middleware('permission:contabilidad.subvenciones.importar');
            Route::post('/subvenciones/{settlement}/aprobar', [AccountingSubsidyController::class, 'approve'])->middleware('permission:contabilidad.subvenciones.aprobar');
            Route::post('/subvenciones/{settlement}/contabilizar', [AccountingSubsidyController::class, 'post'])->middleware('permission:contabilidad.subvenciones.contabilizar');
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
        Route::post('/entregas', [PanolEntregaController::class, 'store'])->middleware('permission:solicitar_materiales_panol');
        Route::get('/entregas/{delivery}', [PanolEntregaController::class, 'show'])->middleware('permission:ver_modulo_centro_apuntes');
        Route::put('/entregas/{delivery}', [PanolEntregaController::class, 'update'])->middleware('permission:solicitar_materiales_panol');
        Route::delete('/entregas/{delivery}', [PanolEntregaController::class, 'destroy'])->middleware('permission:aprobar_entregas_panol');
        Route::post('/entregas/{delivery}/approve', [PanolEntregaController::class, 'approve'])->middleware('permission:aprobar_entregas_panol');
        Route::post('/entregas/{delivery}/reject', [PanolEntregaController::class, 'reject'])->middleware('permission:aprobar_entregas_panol');
        Route::post('/entregas/{delivery}/annul', [PanolEntregaController::class, 'annul'])->middleware('permission:aprobar_entregas_panol');
        Route::post('/entregas/{delivery}/deliver', [PanolEntregaController::class, 'deliver'])->middleware('permission:registrar_entrega_materiales_panol');

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
            ->middleware('permission:aprobar_reservas');
        Route::put('/reservations/{dependencyReservation}/reject', [DependencyReservationController::class, 'reject'])
            ->middleware('permission:rechazar_reservas');
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
    Route::get('/maintenance/reports', MaintenanceReportController::class)
        ->middleware('permission:ver_reportes_mantencion');
    Route::get('/maintenance/work-orders/catalogs', [MaintenanceWorkOrderController::class, 'catalogs'])
        ->middleware('permission:ver_mantencion');
    Route::get('/maintenance/work-orders/dependency-options', [MaintenanceWorkOrderController::class, 'dependencyOptions'])
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
    Route::post('/maintenance/work-orders/{maintenanceWorkOrder}/request-closure', [MaintenanceWorkOrderController::class, 'requestClosure'])
        ->middleware('permission:editar_ot');
    Route::post('/maintenance/work-orders/{maintenanceWorkOrder}/close', [MaintenanceWorkOrderController::class, 'close'])
        ->middleware('permission:editar_ot');
    Route::delete('/maintenance/work-orders/{maintenanceWorkOrder}/photos/{photo}', [MaintenanceWorkOrderController::class, 'deletePhoto'])
        ->middleware('permission:editar_ot');
    Route::delete('/maintenance/work-orders/{maintenanceWorkOrder}', [MaintenanceWorkOrderController::class, 'destroy'])
        ->middleware('permission:editar_ot');

    // Mantención: visitas
    Route::get('/maintenance/visits/catalogs', [MaintenanceVisitController::class, 'catalogs'])
        ->middleware('permission:ver_visitas_mantencion');
    Route::post('/maintenance/visits/planning/preview', [MaintenanceVisitPlanningController::class, 'preview']);
    Route::post('/maintenance/visits/planning/confirm', [MaintenanceVisitPlanningController::class, 'confirm']);
    Route::get('/maintenance/visits/{maintenanceVisit}/checklist', [MaintenanceVisitController::class, 'checklist'])
        ->middleware('permission:ver_visitas_mantencion');
    Route::post('/maintenance/visits/{maintenanceVisit}/checklist', [MaintenanceVisitController::class, 'upsertChecklist'])
        ->middleware('permission:gestionar_visitas_mantencion');
    Route::post('/maintenance/visits/{maintenanceVisit}/checklist-photo', [MaintenanceVisitController::class, 'uploadChecklistPhoto'])
        ->middleware('permission:gestionar_visitas_mantencion');
    Route::delete('/maintenance/visits/{maintenanceVisit}/checklist-photos/{photo}', [MaintenanceVisitController::class, 'deleteChecklistPhoto'])
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

    // Abastecimiento: catálogo, compras y entregas trazables sobre el inventario central.
    Route::prefix('supplies')->group(function () {
        Route::get('/storerooms', [SupplyStoreroomController::class, 'index'])
            ->middleware('permission:ver_abastecimiento');
        Route::post('/storerooms', [SupplyStoreroomController::class, 'store'])
            ->middleware('permission:gestionar_insumos_abastecimiento');
        Route::get('/storerooms/inventory-candidates', [SupplyStoreroomController::class, 'candidates'])
            ->middleware('permission:ver_abastecimiento');
        Route::post('/storerooms/{storeroom}/items', [SupplyStoreroomController::class, 'attachItems'])
            ->middleware('permission:gestionar_insumos_abastecimiento');
        Route::get('/inventory-items/{item}/image', [SupplyStoreroomController::class, 'inventoryImage'])
            ->middleware('permission:ver_abastecimiento');

        Route::get('/catalogs', [SupplyItemController::class, 'catalogs'])
            ->middleware('permission:ver_abastecimiento');
        Route::get('/items', [SupplyItemController::class, 'index'])
            ->middleware('permission:ver_abastecimiento');
        Route::post('/items', [SupplyItemController::class, 'store'])
            ->middleware('permission:gestionar_insumos_abastecimiento');
        Route::get('/items/{item}', [SupplyItemController::class, 'show'])
            ->middleware('permission:ver_abastecimiento');
        Route::put('/items/{item}', [SupplyItemController::class, 'update'])
            ->middleware('permission:gestionar_insumos_abastecimiento');
        Route::delete('/items/{item}', [SupplyItemController::class, 'destroy'])
            ->middleware('permission:gestionar_insumos_abastecimiento');
        Route::post('/items/{item}/photo', [SupplyItemController::class, 'storePhoto'])
            ->middleware('permission:gestionar_insumos_abastecimiento');
        Route::get('/items/{item}/photo', [SupplyItemController::class, 'photo'])
            ->middleware('permission:ver_abastecimiento');

        Route::get('/receipts', [SupplyReceiptController::class, 'index'])
            ->middleware('permission:ver_abastecimiento');
        Route::post('/receipts', [SupplyReceiptController::class, 'store'])
            ->middleware('permission:registrar_compras_abastecimiento');

        Route::get('/deliveries', [SupplyDeliveryController::class, 'index'])
            ->middleware('permission:ver_abastecimiento');
        Route::post('/deliveries', [SupplyDeliveryController::class, 'store'])
            ->middleware('permission:registrar_entregas_abastecimiento');
        Route::get('/deliveries/{delivery}', [SupplyDeliveryController::class, 'show'])
            ->middleware('permission:exportar_actas_abastecimiento');

        Route::get('/requests', [SupplyRequestController::class, 'index'])
            ->middleware('permission:ver_solicitudes_abastecimiento');
        Route::post('/requests', [SupplyRequestController::class, 'store'])
            ->middleware('permission:crear_solicitudes_abastecimiento');
        Route::get('/requests/{supplyRequest}', [SupplyRequestController::class, 'show'])
            ->middleware('permission:ver_solicitudes_abastecimiento');
        Route::get('/request-items/{item}/photo', [SupplyRequestController::class, 'photo'])
            ->middleware('permission:ver_solicitudes_abastecimiento');
    });

    // Inventario
    Route::prefix('inventory')->group(function () {
        // Gestión por dependencias
        Route::get('/management/dependencies', [InventoryManagementController::class, 'index'])
            ->middleware('permission:ver_inventario');
        Route::get('/management/dependencies/{dependency}', [InventoryManagementController::class, 'show'])
            ->middleware('permission:ver_inventario');
        Route::post('/management/dependencies/{dependency}/audits', [InventoryManagementController::class, 'storeAudit'])
            ->middleware('permission:editar_inventario');

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
        Route::get('/items/similar', [InventoryItemController::class, 'similar'])
            ->middleware('permission:ver_inventario');
        Route::get('/items', [InventoryItemController::class, 'index'])
            ->middleware('permission:ver_inventario');
        Route::post('/items', [InventoryItemController::class, 'store'])
            ->middleware('permission:crear_inventario');
        Route::get('/items/{item}', [InventoryItemController::class, 'show'])
            ->middleware('permission:ver_inventario');
        Route::get('/items/{item}/image', [InventoryItemController::class, 'image'])
            ->middleware('permission:ver_inventario');
        Route::put('/items/{item}', [InventoryItemController::class, 'update'])
            ->middleware('permission:editar_inventario');
        Route::delete('/items/{item}', [InventoryItemController::class, 'destroy'])
            ->middleware('permission:eliminar_inventario');

        // Fotos
        Route::post('/items/{item}/photos', [InventoryItemPhotoController::class, 'store'])
            ->middleware('permission:editar_inventario');
        Route::get('/photos/{photo}/image', [InventoryItemPhotoController::class, 'image'])
            ->middleware('permission:ver_inventario');
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
    Route::get('/staff/import-template', [StaffController::class, 'importTemplate'])
        ->middleware('permission:gestionar_funcionarios');
    Route::post('/staff/import', [StaffController::class, 'import'])
        ->middleware('permission:gestionar_funcionarios');
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
