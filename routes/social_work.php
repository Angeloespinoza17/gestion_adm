<?php

use App\Http\Controllers\Inspectoria\InspectoriaPickupRestrictionController;
use App\Http\Controllers\SocialWork\CaseController;
use App\Http\Controllers\SocialWork\ConfigurationController;
use App\Http\Controllers\SocialWork\DashboardController;
use App\Http\Controllers\SocialWork\DocumentController;
use App\Http\Controllers\SocialWork\StudentController;
use App\Http\Controllers\SocialWork\SupportController;
use App\Http\Controllers\SocialWork\WorkflowController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('social-work')->group(function () {
    Route::get('/dashboard', DashboardController::class)->middleware('permission:social_work.dashboard.view');
    Route::get('/students', [StudentController::class, 'index'])->middleware('permission:social_work.students.view');
    Route::get('/students/{student}', [StudentController::class, 'show'])->middleware('permission:social_work.student_profile.view');
    Route::get('/students/{student}/timeline', [StudentController::class, 'timeline'])->middleware('permission:social_work.student_profile.view');
    Route::get('/support-matrix', [SupportController::class, 'supportMatrix'])->middleware('permission:social_work.medical.manage');
    Route::put('/support-matrix/{student}', [SupportController::class, 'updateSupportProfile'])->middleware('permission:social_work.student_profile.update');

    Route::get('/cases', [CaseController::class, 'index'])->middleware('permission:social_work.cases.view');
    Route::post('/cases', [CaseController::class, 'store'])->middleware('permission:social_work.cases.create');
    Route::get('/cases/{case}', [CaseController::class, 'show'])->middleware('permission:social_work.cases.view');
    Route::post('/cases/{case}/export', [CaseController::class, 'export'])->middleware(['permission:social_work.reports.export', 'throttle:20,1']);
    Route::patch('/cases/{case}', [CaseController::class, 'update'])->middleware('permission:social_work.cases.update');
    Route::post('/cases/{case}/assign', [CaseController::class, 'assign'])->middleware('permission:social_work.cases.assign');
    Route::post('/cases/{case}/change-status', [CaseController::class, 'changeStatus'])->middleware('permission:social_work.cases.update');
    Route::post('/cases/{case}/close', [CaseController::class, 'close'])->middleware('permission:social_work.cases.close');
    Route::post('/cases/{case}/reopen', [CaseController::class, 'reopen'])->middleware('permission:social_work.cases.reopen');
    Route::get('/cases/{case}/timeline', [CaseController::class, 'timeline'])->middleware('permission:social_work.cases.view');

    Route::get('/cases/{case}/interventions', [WorkflowController::class, 'interventions'])->middleware('permission:social_work.cases.view');
    Route::post('/cases/{case}/interventions', [WorkflowController::class, 'storeIntervention']);
    Route::patch('/interventions/{intervention}', [WorkflowController::class, 'updateIntervention'])->middleware('throttle:60,1');
    Route::post('/interventions/{intervention}/convert', [WorkflowController::class, 'convertIntervention'])->middleware('permission:social_work.actions.manage');
    Route::post('/cases/{case}/protocols', [WorkflowController::class, 'activateProtocol'])->middleware('permission:social_work.protocols.manage');
    Route::post('/case-protocols/{activation}/advance', [WorkflowController::class, 'advanceProtocol'])->middleware('permission:social_work.protocols.manage');
    Route::post('/case-protocols/{activation}/links', [WorkflowController::class, 'linkProtocolStep'])->middleware('permission:social_work.protocols.manage');
    Route::post('/cases/{case}/protocol-zero', [WorkflowController::class, 'protocolZero'])->middleware('permission:social_work.protocols.manage');
    Route::post('/cases/{case}/requested-information', [WorkflowController::class, 'addRequestedInformation'])->middleware('permission:social_work.cases.update');
    Route::post('/cases/{case}/risk-assessments', [WorkflowController::class, 'assessRisk'])->middleware('permission:social_work.cases.update');

    Route::get('/referral-students', [WorkflowController::class, 'referralStudents'])->middleware('permission:social_work.referrals.submit');
    Route::get('/referrals', [WorkflowController::class, 'referrals'])->middleware('permission:social_work.referrals.submit');
    Route::post('/referrals', [WorkflowController::class, 'storeReferral'])->middleware('permission:social_work.referrals.submit');
    Route::post('/referrals/{referral}/convert-to-case', [WorkflowController::class, 'convertReferral'])->middleware('permission:social_work.referrals.manage');
    Route::post('/pedagogical-reports/request', [WorkflowController::class, 'requestPedagogicalReport'])->middleware('permission:social_work.pedagogical_reports.request');
    Route::post('/pedagogical-reports/{report}/respond', [WorkflowController::class, 'respondPedagogicalReport'])->middleware('permission:social_work.pedagogical_reports.respond');

    Route::get('/alerts', [WorkflowController::class, 'alerts'])->middleware('permission:social_work.alerts.manage');
    Route::post('/alerts/{alert}/resolve', [WorkflowController::class, 'resolveAlert'])->middleware('permission:social_work.alerts.manage');
    Route::patch('/alerts/{alert}', [WorkflowController::class, 'updateAlert'])->middleware('permission:social_work.alerts.manage');
    Route::post('/alerts/{alert}/comments', [WorkflowController::class, 'commentAlert'])->middleware('permission:social_work.alerts.manage');
    Route::get('/reports', [WorkflowController::class, 'reports'])->middleware('permission:social_work.reports.create');
    Route::post('/cases/{case}/master-report', [WorkflowController::class, 'masterReport'])->middleware('permission:social_work.reports.create');
    Route::post('/reports/{report}/versions', [WorkflowController::class, 'versionReport'])->middleware('permission:social_work.reports.create');
    Route::post('/reports/{report}/approve', [WorkflowController::class, 'approveReport'])->middleware('permission:social_work.reports.approve');
    Route::get('/calendar', [WorkflowController::class, 'calendar'])->middleware('permission:social_work.dashboard.view');

    Route::get('/programs', [SupportController::class, 'programs'])->middleware('permission:social_work.student_profile.view');
    Route::post('/programs', [SupportController::class, 'storeProgram'])->middleware('permission:social_work.junaeb.manage');
    Route::get('/protection-measures', [SupportController::class, 'protectionMeasures'])->middleware('permission:social_work.student_profile.view');
    Route::post('/protection-measures', [SupportController::class, 'storeProtectionMeasure'])->middleware('permission:social_work.cases.update');
    Route::get('/junaeb/benefits', [SupportController::class, 'benefits'])->middleware('permission:social_work.junaeb.manage');
    Route::get('/junaeb/student-options', [SupportController::class, 'studentOptions'])->middleware('permission:social_work.junaeb.manage');
    Route::post('/junaeb/benefits', [SupportController::class, 'storeBenefit'])->middleware('permission:social_work.junaeb.manage');
    Route::post('/junaeb/benefits/{benefit}/deliveries', [SupportController::class, 'deliver'])->middleware('permission:social_work.junaeb.manage');
    Route::post('/junaeb/deliveries/{delivery}/clone', [SupportController::class, 'cloneDelivery'])->middleware('permission:social_work.junaeb.manage');
    Route::post('/junaeb/deliveries/bulk', [SupportController::class, 'bulkDeliver'])->middleware('permission:social_work.junaeb.manage');
    Route::get('/transport-passes', [SupportController::class, 'transportPasses'])->middleware('permission:social_work.junaeb.manage');
    Route::post('/transport-passes', [SupportController::class, 'storeTransportPass'])->middleware('permission:social_work.junaeb.manage');
    Route::patch('/transport-passes/{pass}', [SupportController::class, 'updateTransportPass'])->middleware('permission:social_work.junaeb.manage');
    Route::get('/medical-services', [SupportController::class, 'medicalServices'])->middleware('permission:social_work.medical.manage');
    Route::post('/medical-services', [SupportController::class, 'storeMedicalService'])->middleware('permission:social_work.medical.manage');
    Route::get('/support-devices', [SupportController::class, 'supportDevices'])->middleware('permission:social_work.medical.manage');
    Route::post('/support-devices', [SupportController::class, 'storeSupportDevice'])->middleware('permission:social_work.medical.manage');
    Route::get('/junaeb/deliveries', [SupportController::class, 'deliveries'])->middleware('permission:social_work.junaeb.manage');

    Route::get('/pickup-restrictions', [InspectoriaPickupRestrictionController::class, 'index'])->middleware('permission:social_work.pickup_restrictions.manage');
    Route::post('/pickup-restrictions', [InspectoriaPickupRestrictionController::class, 'store'])->middleware('permission:social_work.pickup_restrictions.manage');
    Route::put('/pickup-restrictions/{restriction}', [InspectoriaPickupRestrictionController::class, 'update'])->middleware('permission:social_work.pickup_restrictions.manage');
    Route::delete('/pickup-restrictions/{restriction}', [InspectoriaPickupRestrictionController::class, 'destroy'])->middleware('permission:social_work.pickup_restrictions.manage');

    Route::post('/documents', [DocumentController::class, 'store'])->middleware('permission:social_work.cases.update');
    Route::get('/documents/{document}/download', [DocumentController::class, 'download'])->middleware('permission:social_work.cases.view');
    Route::post('/medical-certificates', [DocumentController::class, 'storeCertificate'])->middleware('permission:social_work.medical.manage');
    Route::get('/medical-certificates', [DocumentController::class, 'certificates'])->middleware('permission:social_work.medical.manage');
    Route::get('/medical-certificates/{certificate}/download', [DocumentController::class, 'downloadCertificate'])->middleware('permission:social_work.medical_documents.view');

    Route::get('/catalogs', [ConfigurationController::class, 'catalogs'])->middleware('permission:social_work.dashboard.view');
    Route::get('/form-templates', [ConfigurationController::class, 'templates'])->middleware('permission:social_work.templates.manage');
    Route::post('/form-templates', [ConfigurationController::class, 'storeTemplate'])->middleware('permission:social_work.templates.manage');
    Route::get('/protocols', [ConfigurationController::class, 'protocols'])->middleware('permission:social_work.protocols.manage');
    Route::post('/protocols', [ConfigurationController::class, 'storeProtocol'])->middleware('permission:social_work.protocols.manage');
    Route::get('/audit', [ConfigurationController::class, 'audit'])->middleware('permission:social_work.audit.view');
});
