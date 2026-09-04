<?php

use App\Http\Controllers\PedagogicalManagement\CanvaConnectionController;
use App\Http\Controllers\PedagogicalManagement\CanvaOAuthCallbackController;
use App\Http\Controllers\PedagogicalManagement\CanvaTemplateController;
use App\Http\Controllers\PedagogicalManagement\ClassPresentationActionController;
use App\Http\Controllers\PedagogicalManagement\ClassPresentationCanvaController;
use App\Http\Controllers\PedagogicalManagement\ClassPresentationCatalogController;
use App\Http\Controllers\PedagogicalManagement\ClassPresentationController;
use App\Http\Controllers\PedagogicalManagement\ClassPresentationFileController;
use App\Http\Controllers\PedagogicalManagement\PedagogicalAiReportController;
use App\Http\Controllers\PedagogicalManagement\PedagogicalAiWorkspaceController;
use App\Http\Controllers\PedagogicalManagement\PedagogicalCatalogController;
use App\Http\Controllers\PedagogicalManagement\PedagogicalCoordinatorAssignmentController;
use App\Http\Controllers\PedagogicalManagement\PedagogicalDocumentReviewController;
use App\Http\Controllers\PedagogicalManagement\PedagogicalGuidanceDocumentController;
use App\Http\Controllers\PedagogicalManagement\PedagogicalInstrumentAnalysisController;
use App\Http\Controllers\PedagogicalManagement\PedagogicalInstrumentController;
use App\Http\Controllers\PedagogicalManagement\PedagogicalInstrumentFileController;
use App\Http\Controllers\PedagogicalManagement\PedagogicalPrintRequestController;
use App\Http\Controllers\PedagogicalManagement\PedagogicalStatisticsController;
use App\Http\Controllers\PedagogicalManagement\PedagogicalValidationResultController;
use Illuminate\Support\Facades\Route;

Route::get('/integraciones/canva/callback', CanvaOAuthCallbackController::class)
    ->middleware('throttle:20,1')
    ->name('api.canva.oauth.callback');

Route::prefix('pedagogical-management')
    ->middleware(['auth:sanctum', 'permission:pedagogical-instruments.view'])
    ->name('api.pedagogical-management.')
    ->group(function (): void {
        Route::get('/catalogs', PedagogicalCatalogController::class)->name('catalogs');
        Route::get('/instruments', [PedagogicalInstrumentController::class, 'index'])->name('instruments.index');
        Route::post('/instruments', [PedagogicalInstrumentController::class, 'store'])
            ->middleware(['permission:pedagogical-instruments.create', 'throttle:10,1'])->name('instruments.store');
        Route::get('/instruments/{instrument}', [PedagogicalInstrumentController::class, 'show'])->name('instruments.show');
        Route::patch('/instruments/{instrument}', [PedagogicalInstrumentController::class, 'update'])
            ->middleware('permission:pedagogical-instruments.update')->name('instruments.update');
        Route::post('/instruments/{instrument}/archive', [PedagogicalInstrumentController::class, 'archive'])
            ->middleware('permission:pedagogical-instruments.archive')->name('instruments.archive');
        Route::post('/instruments/{instrument}/files', [PedagogicalInstrumentFileController::class, 'store'])
            ->middleware(['permission:pedagogical-instruments.update', 'throttle:10,1'])->name('instruments.files.store');
        Route::get('/instruments/{instrument}/files/{file}/view', [PedagogicalInstrumentFileController::class, 'view'])
            ->middleware('permission:pedagogical-instruments.download')->name('instruments.files.view');
        Route::get('/instruments/{instrument}/files/{file}/download', [PedagogicalInstrumentFileController::class, 'download'])
            ->middleware('permission:pedagogical-instruments.download')->name('instruments.files.download');
        Route::post('/instruments/{instrument}/analyses', [PedagogicalInstrumentAnalysisController::class, 'store'])
            ->middleware(['permission:pedagogical-instruments.analyze', 'throttle:20,1'])->name('instruments.analyses.store');
        Route::get('/instruments/{instrument}/analyses/{analysis}', [PedagogicalInstrumentAnalysisController::class, 'show'])
            ->name('instruments.analyses.show');
        Route::post('/instruments/{instrument}/ai-reports', [PedagogicalAiReportController::class, 'store'])
            ->middleware(['permission:pedagogical-instruments.ai-report', 'throttle:10,1'])->name('instruments.ai-reports.store');
        Route::get('/instruments/{instrument}/ai-reports/{aiReport}', [PedagogicalAiReportController::class, 'show'])
            ->name('instruments.ai-reports.show');
        Route::get('/document-review', [PedagogicalDocumentReviewController::class, 'index'])
            ->middleware('permission:pedagogical-instruments.decide')->name('document-review.index');
        Route::get('/statistics', [PedagogicalStatisticsController::class, 'index'])
            ->middleware('permission:pedagogical-instruments.statistics')->name('statistics.index');
        Route::get('/statistics/instruments/{instrument}', [PedagogicalStatisticsController::class, 'instrument'])
            ->middleware('permission:pedagogical-instruments.statistics')->name('statistics.instruments.show');
        Route::prefix('ai-workspace')
            ->middleware('permission:pedagogical-instruments.ai-workspace')
            ->name('ai-workspace.')
            ->group(function (): void {
                Route::get('/catalogs', [PedagogicalAiWorkspaceController::class, 'catalogs'])->name('catalogs');
                Route::get('/reviews', [PedagogicalAiWorkspaceController::class, 'index'])->name('index');
                Route::post('/reviews', [PedagogicalAiWorkspaceController::class, 'store'])
                    ->middleware('throttle:10,1')->name('store');
                Route::get('/reviews/{instrument}', [PedagogicalAiWorkspaceController::class, 'show'])->name('show');
                Route::post('/reviews/{instrument}/reports', [PedagogicalAiWorkspaceController::class, 'regenerate'])
                    ->middleware('throttle:10,1')->name('reports.store');
            });
        Route::post('/instruments/{instrument}/reviews', [PedagogicalDocumentReviewController::class, 'store'])
            ->middleware('permission:pedagogical-instruments.decide')->name('instruments.reviews.store');
        Route::get('/guidance-documents', [PedagogicalGuidanceDocumentController::class, 'index'])
            ->middleware('permission:pedagogical-guidance.manage')->name('guidance-documents.index');
        Route::post('/guidance-documents', [PedagogicalGuidanceDocumentController::class, 'store'])
            ->middleware('permission:pedagogical-guidance.manage')->name('guidance-documents.store');
        Route::patch('/guidance-documents/{guidanceDocument}', [PedagogicalGuidanceDocumentController::class, 'update'])
            ->middleware('permission:pedagogical-guidance.manage')->name('guidance-documents.update');
        Route::get('/coordinator-assignments', [PedagogicalCoordinatorAssignmentController::class, 'index'])
            ->middleware('permission:pedagogical-coordinators.configure')->name('coordinator-assignments.index');
        Route::put('/coordinator-assignments', [PedagogicalCoordinatorAssignmentController::class, 'update'])
            ->middleware('permission:pedagogical-coordinators.configure')->name('coordinator-assignments.update');
        Route::post('/validation-results/{result}/resolve', [PedagogicalValidationResultController::class, 'resolve'])
            ->middleware('permission:pedagogical-instruments.resolve-validations')->name('validation-results.resolve');
        Route::post('/validation-results/{result}/reopen', [PedagogicalValidationResultController::class, 'reopen'])
            ->middleware('permission:pedagogical-instruments.resolve-validations')->name('validation-results.reopen');
    });

Route::prefix('pedagogical-print-center')
    ->middleware(['auth:sanctum', 'permission:pedagogical-print-requests.view'])
    ->name('api.pedagogical-print-center.')
    ->group(function (): void {
        Route::get('/requests', [PedagogicalPrintRequestController::class, 'index'])->name('requests.index');
        Route::get('/requests/{printRequest}/file', [PedagogicalPrintRequestController::class, 'file'])->name('requests.file');
        Route::post('/requests/{printRequest}/actions', [PedagogicalPrintRequestController::class, 'action'])->name('requests.actions');
    });

Route::prefix('gestion-pedagogica')
    ->middleware(['auth:sanctum', 'permission:class-presentations.view'])
    ->name('api.class-presentations.')
    ->group(function (): void {
        Route::get('/generador-clases/opciones', [ClassPresentationCatalogController::class, 'options'])->name('options');
        Route::get('/cursos/{course}/asignaturas', [ClassPresentationCatalogController::class, 'subjects'])->name('subjects');
        Route::get('/asignaturas/{subject}/unidades', [ClassPresentationCatalogController::class, 'units'])->name('units');
        Route::get('/unidades/{unit}/objetivos', [ClassPresentationCatalogController::class, 'objectives'])->name('objectives');
        Route::post('/generador-clases/titulos', [ClassPresentationCatalogController::class, 'titles'])
            ->middleware('permission:class-presentations.create')->name('titles');
        Route::get('/canva/conexion', [CanvaConnectionController::class, 'show'])
            ->middleware('permission:class-presentations.create')->name('canva.connection.show');
        Route::post('/canva/autorizacion', [CanvaConnectionController::class, 'begin'])
            ->middleware(['permission:class-presentations.create', 'throttle:10,1'])->name('canva.authorization.begin');
        Route::delete('/canva/conexion', [CanvaConnectionController::class, 'destroy'])
            ->middleware(['permission:class-presentations.create', 'throttle:10,1'])->name('canva.connection.destroy');
        Route::get('/canva/plantillas', [CanvaTemplateController::class, 'index'])
            ->middleware(['permission:class-presentations.create', 'throttle:30,1'])->name('canva.templates.index');
        Route::post('/canva/plantillas/{brandTemplateId}/validar', [CanvaTemplateController::class, 'validateTemplate'])
            ->where('brandTemplateId', '[A-Za-z0-9_-]+')
            ->middleware(['permission:class-presentations.create', 'throttle:30,1'])->name('canva.templates.validate');
        Route::get('/presentaciones', [ClassPresentationController::class, 'index'])->name('index');
        Route::post('/presentaciones', [ClassPresentationController::class, 'store'])
            ->middleware(['permission:class-presentations.create', 'throttle:5,1'])->name('store');
        Route::get('/presentaciones/{presentation}', [ClassPresentationController::class, 'show'])->name('show');
        Route::get('/presentaciones/{presentation}/estado', [ClassPresentationController::class, 'status'])->name('status');
        Route::post('/presentaciones/{presentation}/canva/sincronizar', [ClassPresentationCanvaController::class, 'sync'])
            ->middleware(['permission:class-presentations.create', 'throttle:10,1'])->name('canva.sync');
        Route::post('/presentaciones/{presentation}/canva/enlace-edicion', [ClassPresentationCanvaController::class, 'editLink'])
            ->middleware(['permission:class-presentations.create', 'throttle:30,1'])->name('canva.edit-link');
        Route::post('/presentaciones/{presentation}/regenerar', [ClassPresentationActionController::class, 'regenerate'])
            ->middleware(['permission:class-presentations.regenerate', 'throttle:5,1'])->name('regenerate');
        Route::post('/presentaciones/{presentation}/reintentar', [ClassPresentationActionController::class, 'retry'])
            ->middleware(['permission:class-presentations.regenerate', 'throttle:5,1'])->name('retry');
        Route::post('/presentaciones/{presentation}/archivar', [ClassPresentationActionController::class, 'archive'])
            ->middleware('permission:class-presentations.archive')->name('archive');
        Route::get('/presentaciones/{presentation}/archivos/{file}/descargar', [ClassPresentationFileController::class, 'download'])
            ->middleware('permission:class-presentations.download')->name('files.download');
    });
