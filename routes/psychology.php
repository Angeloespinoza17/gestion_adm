<?php

use App\Http\Controllers\Psychology\PsychologyCaseController;
use App\Http\Controllers\Psychology\PsychologyCatalogController;
use App\Http\Controllers\Psychology\PsychologyConfigurationController;
use App\Http\Controllers\Psychology\PsychologyDashboardController;
use App\Http\Controllers\Psychology\PsychologyDocumentController;
use App\Http\Controllers\Psychology\PsychologyReferralController;
use App\Http\Controllers\Psychology\PsychologyReportController;
use App\Http\Controllers\Psychology\PsychologyWorkflowController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'permission:psychology.access'])->prefix('psychology')->group(function () {
    Route::get('/catalogs', PsychologyCatalogController::class);
    Route::get('/students', [PsychologyCatalogController::class, 'students']);
    Route::get('/dashboard', PsychologyDashboardController::class);

    Route::get('/referrals', [PsychologyReferralController::class, 'index']);
    Route::post('/referrals', [PsychologyReferralController::class, 'store'])->middleware('permission:psychology.referrals.create');
    Route::get('/referrals/{referral}', [PsychologyReferralController::class, 'show']);
    Route::put('/referrals/{referral}', [PsychologyReferralController::class, 'update']);
    Route::post('/referrals/{referral}/transition', [PsychologyReferralController::class, 'transition'])->middleware('throttle:60,1');
    Route::post('/referrals/{referral}/assign', [PsychologyReferralController::class, 'assign'])->middleware('permission:psychology.referrals.assign');
    Route::post('/referrals/{referral}/open-case', [PsychologyCaseController::class, 'open'])->middleware('permission:psychology.cases.create');

    Route::get('/cases', [PsychologyCaseController::class, 'index']);
    Route::get('/cases/{case}', [PsychologyCaseController::class, 'show']);
    Route::post('/cases/{case}/assign', [PsychologyCaseController::class, 'assign'])->middleware('permission:psychology.cases.reassign');
    Route::post('/cases/{case}/close', [PsychologyCaseController::class, 'close'])->middleware('permission:psychology.cases.close');
    Route::post('/cases/{case}/reopen', [PsychologyCaseController::class, 'reopen'])->middleware('permission:psychology.cases.reopen');
    Route::post('/cases/{case}/activities', [PsychologyWorkflowController::class, 'storeActivity'])->middleware('permission:psychology.sessions.create');
    Route::post('/activities/{activity}/finalize', [PsychologyWorkflowController::class, 'finalizeActivity'])->middleware('permission:psychology.sessions.create');
    Route::post('/activities/{activity}/addenda', [PsychologyWorkflowController::class, 'addAddendum'])->middleware('permission:psychology.sessions.create');
    Route::post('/cases/{case}/plans', [PsychologyWorkflowController::class, 'storePlan'])->middleware('permission:psychology.sessions.create');
    Route::post('/plans/{plan}/versions', [PsychologyWorkflowController::class, 'versionPlan'])->middleware('permission:psychology.sessions.create');
    Route::post('/cases/{case}/risk-assessments', [PsychologyWorkflowController::class, 'storeRisk'])->middleware(['permission:psychology.risk.create', 'throttle:30,1']);
    Route::post('/risk-assessments/{risk}/acknowledge', [PsychologyWorkflowController::class, 'acknowledgeRisk'])->middleware('permission:psychology.risk.view');
    Route::post('/cases/{case}/tasks', [PsychologyWorkflowController::class, 'storeTask'])->middleware('permission:psychology.sessions.create');
    Route::patch('/tasks/{task}', [PsychologyWorkflowController::class, 'updateTask'])->middleware('permission:psychology.sessions.create');
    Route::post('/cases/{case}/consents', [PsychologyWorkflowController::class, 'storeConsent'])->middleware('permission:psychology.sessions.create');
    Route::post('/cases/{case}/external-referrals', [PsychologyWorkflowController::class, 'storeExternalReferral'])->middleware('permission:psychology.sessions.create');
    Route::post('/cases/{case}/feedback', [PsychologyWorkflowController::class, 'storeFeedback'])->middleware('permission:psychology.sessions.create');

    Route::post('/documents', [PsychologyDocumentController::class, 'store'])->middleware(['permission:psychology.documents.upload', 'throttle:20,1']);
    Route::get('/documents/{document}/download', [PsychologyDocumentController::class, 'download'])->middleware(['permission:psychology.documents.download', 'throttle:60,1']);
    Route::delete('/documents/{document}', [PsychologyDocumentController::class, 'destroy'])->middleware('permission:psychology.documents.upload');
    Route::get('/calendar', [PsychologyWorkflowController::class, 'calendar']);
    Route::get('/reports', PsychologyReportController::class)->middleware('permission:psychology.reports.aggregate');
    Route::get('/reports/export.csv', [PsychologyReportController::class, 'csv'])->middleware(['permission:psychology.reports.nominal', 'throttle:10,1']);
    Route::post('/exports', [PsychologyReportController::class, 'queue'])->middleware(['permission:psychology.reports.nominal', 'throttle:10,1']);
    Route::get('/exports/{export}', [PsychologyReportController::class, 'exportStatus'])->middleware('permission:psychology.reports.nominal');
    Route::get('/exports/{export}/download', [PsychologyReportController::class, 'downloadExport'])->middleware(['permission:psychology.reports.nominal', 'throttle:10,1']);

    Route::post('/configuration/catalogs', [PsychologyConfigurationController::class, 'storeCatalog'])->middleware('permission:psychology.config.manage');
    Route::put('/configuration/settings', [PsychologyConfigurationController::class, 'updateSettings'])->middleware('permission:psychology.config.manage');
    Route::get('/audit', [PsychologyConfigurationController::class, 'audit'])->middleware('permission:psychology.audit.view');
});
