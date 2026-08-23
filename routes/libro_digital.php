<?php

use App\Http\Controllers\LibroDigital\AbsenceCaseController;
use App\Http\Controllers\LibroDigital\AmendmentController;
use App\Http\Controllers\LibroDigital\AssessmentController;
use App\Http\Controllers\LibroDigital\AttendanceClosureController;
use App\Http\Controllers\LibroDigital\AttendanceController;
use App\Http\Controllers\LibroDigital\AuditController;
use App\Http\Controllers\LibroDigital\BookController;
use App\Http\Controllers\LibroDigital\CatalogController;
use App\Http\Controllers\LibroDigital\CoexistenceController;
use App\Http\Controllers\LibroDigital\ConfigurationController;
use App\Http\Controllers\LibroDigital\CurriculumController;
use App\Http\Controllers\LibroDigital\CurriculumImportController;
use App\Http\Controllers\LibroDigital\CurriculumProgramController;
use App\Http\Controllers\LibroDigital\EarlyWithdrawalController;
use App\Http\Controllers\LibroDigital\EdeController;
use App\Http\Controllers\LibroDigital\LateArrivalController;
use App\Http\Controllers\LibroDigital\LessonRecordController;
use App\Http\Controllers\LibroDigital\OverviewController;
use App\Http\Controllers\LibroDigital\ParvulariaController;
use App\Http\Controllers\LibroDigital\PieController;
use App\Http\Controllers\LibroDigital\ReportController;
use App\Http\Controllers\LibroDigital\SessionController;
use App\Http\Controllers\LibroDigital\StatisticsController;
use App\Http\Controllers\LibroDigital\SubjectController;
use Illuminate\Support\Facades\Route;

Route::prefix('libro-digital/v1')
    ->middleware(['auth:sanctum', 'lcd.correlation', 'permission:libro_digital.access', 'lcd.enabled'])
    ->name('api.libro-digital.v1.')
    ->group(function (): void {
        Route::get('/catalogs', CatalogController::class)->name('catalogs');
        Route::get('/overview', OverviewController::class)->name('overview');

        Route::get('/books', [BookController::class, 'index'])->name('books.index');
        Route::post('/books', [BookController::class, 'store'])->middleware('lcd.idempotency')->name('books.store');
        Route::post('/books/bulk-open', [BookController::class, 'bulkOpen'])->middleware('lcd.idempotency')->name('books.bulk-open');
        Route::get('/books/{book}', [BookController::class, 'show'])->name('books.show');
        Route::patch('/books/{book}', [BookController::class, 'update'])->middleware('lcd.idempotency')->name('books.update');
        Route::post('/books/{book}/preflight', [BookController::class, 'preflight'])->middleware('lcd.idempotency')->name('books.preflight');
        Route::post('/books/{book}/open', [BookController::class, 'open'])->middleware('lcd.idempotency')->name('books.open');
        Route::post('/books/{book}/close', [BookController::class, 'close'])->middleware('lcd.idempotency')->name('books.close');
        Route::post('/books/{book}/reopen', [BookController::class, 'reopen'])->middleware('lcd.idempotency')->name('books.reopen');
        Route::get('/books/{book}/roster', [BookController::class, 'roster'])->name('books.roster');

        Route::get('/subjects', [SubjectController::class, 'index'])->name('subjects.index');
        Route::post('/subjects', [SubjectController::class, 'store'])->middleware(['permission:libro_digital.subject_catalog.manage', 'lcd.idempotency'])->name('subjects.store');
        Route::post('/subjects/bulk-status', [SubjectController::class, 'bulkStatus'])->middleware(['permission:libro_digital.subject_catalog.manage', 'lcd.idempotency'])->name('subjects.bulk-status');
        Route::get('/subjects/external-catalog', [SubjectController::class, 'externalCatalog'])->name('subjects.external-catalog');
        Route::put('/subjects/external-mappings', [SubjectController::class, 'updateExternalMappings'])->middleware(['permission:libro_digital.subject_catalog.manage', 'lcd.idempotency'])->name('subjects.external-mappings.update');
        Route::patch('/subjects/{subject}', [SubjectController::class, 'update'])->middleware(['permission:libro_digital.subject_catalog.manage', 'lcd.idempotency'])->name('subjects.update');

        Route::get('/books/{book}/sessions', [SessionController::class, 'index'])->name('sessions.index');
        Route::post('/books/{book}/sessions', [SessionController::class, 'store'])->middleware('lcd.idempotency')->name('sessions.store');
        Route::get('/sessions/{session}', [SessionController::class, 'show'])->name('sessions.show');
        Route::patch('/sessions/{session}', [SessionController::class, 'update'])->middleware('lcd.idempotency')->name('sessions.update');
        Route::post('/sessions/{session}/cancel', [SessionController::class, 'cancel'])->middleware('lcd.idempotency')->name('sessions.cancel');
        Route::post('/sessions/{session}/prepare-signature', [SessionController::class, 'prepareSignature'])->middleware('lcd.idempotency')->name('sessions.prepare-signature');
        Route::post('/sessions/{session}/sign', [SessionController::class, 'sign'])->middleware('lcd.idempotency')->name('sessions.sign');

        Route::get('/sessions/{session}/lesson-record', [LessonRecordController::class, 'show'])->name('lesson.show');
        Route::put('/sessions/{session}/lesson-record', [LessonRecordController::class, 'update'])->middleware('lcd.idempotency')->name('lesson.update');
        Route::get('/curriculum/objectives', [CurriculumController::class, 'objectives'])->name('curriculum.objectives.index');
        Route::get('/curriculum/objectives/visualization', [CurriculumController::class, 'visualization'])
            ->name('curriculum.objectives.visualization');
        Route::get('/curriculum/objectives/{objective}', [CurriculumController::class, 'show'])
            ->where('objective', '[0-9A-Za-z]{1,40}')
            ->name('curriculum.objectives.show');
        Route::get('/curriculum/imports/template', [CurriculumImportController::class, 'template'])->name('curriculum.imports.template');
        Route::get('/curriculum/imports', [CurriculumImportController::class, 'index'])->name('curriculum.imports.index');
        Route::post('/curriculum/imports/validate', [CurriculumImportController::class, 'validateUpload'])
            ->middleware(['permission:libro_digital.curriculum.import', 'lcd.idempotency'])
            ->name('curriculum.imports.validate');
        Route::get('/curriculum/imports/{import}', [CurriculumImportController::class, 'show'])->name('curriculum.imports.show');
        Route::post('/curriculum/imports/{import}/approve', [CurriculumImportController::class, 'approve'])
            ->middleware(['permission:libro_digital.curriculum.approve', 'lcd.idempotency'])
            ->name('curriculum.imports.approve');
        Route::post('/curriculum/imports/{import}/activate', [CurriculumImportController::class, 'activate'])
            ->middleware(['permission:libro_digital.curriculum.activate', 'lcd.idempotency'])
            ->name('curriculum.imports.activate');

        Route::prefix('curriculum/program-catalog')->middleware('permission:libro_digital.curriculum_programs.view')->group(function (): void {
            Route::get('/matrix', [CurriculumProgramController::class, 'matrix'])->name('curriculum.programs.matrix');
            Route::get('/search', [CurriculumProgramController::class, 'search'])->name('curriculum.programs.search');
            Route::get('/programs', [CurriculumProgramController::class, 'index'])->name('curriculum.programs.index');
            Route::post('/programs/manual', [CurriculumProgramController::class, 'createManual'])->middleware(['permission:libro_digital.curriculum_programs.import', 'lcd.idempotency'])->name('curriculum.programs.manual.store');
            Route::get('/programs/{program}', [CurriculumProgramController::class, 'showProgram'])->name('curriculum.programs.show');
            Route::post('/programs/{program}/export-pdf', [CurriculumProgramController::class, 'exportPdf'])->middleware(['permission:libro_digital.curriculum_programs.export_pdf', 'lcd.idempotency'])->name('curriculum.programs.export-pdf');
            Route::post('/programs/{program}/archive', [CurriculumProgramController::class, 'archiveProgram'])->middleware(['permission:libro_digital.curriculum_programs.archive', 'lcd.idempotency'])->name('curriculum.programs.archive');
            Route::get('/imports', [CurriculumProgramController::class, 'imports'])->name('curriculum.program-imports.index');
            Route::post('/imports', [CurriculumProgramController::class, 'upload'])->middleware(['permission:libro_digital.curriculum_programs.import', 'lcd.idempotency'])->name('curriculum.program-imports.store');
            Route::get('/imports/{file}', [CurriculumProgramController::class, 'showImport'])->name('curriculum.program-imports.show');
            Route::post('/imports/{file}/validate', [CurriculumProgramController::class, 'validateImport'])->middleware(['permission:libro_digital.curriculum_programs.review', 'lcd.idempotency'])->name('curriculum.program-imports.validate');
            Route::post('/imports/{file}/publish', [CurriculumProgramController::class, 'publishImport'])->middleware(['permission:libro_digital.curriculum_programs.publish', 'lcd.idempotency'])->name('curriculum.program-imports.publish');
            Route::post('/imports/{file}/reprocess', [CurriculumProgramController::class, 'reprocess'])->middleware(['permission:libro_digital.curriculum_programs.reprocess', 'lcd.idempotency'])->name('curriculum.program-imports.reprocess');
            Route::post('/imports/{file}/archive', [CurriculumProgramController::class, 'archiveImport'])->middleware(['permission:libro_digital.curriculum_programs.archive', 'lcd.idempotency'])->name('curriculum.program-imports.archive');
            Route::patch('/candidates/{candidate}', [CurriculumProgramController::class, 'reviewCandidate'])->middleware(['permission:libro_digital.curriculum_programs.review', 'lcd.idempotency'])->name('curriculum.program-imports.candidates.review');
            Route::post('/conflicts/{conflict}/resolve', [CurriculumProgramController::class, 'resolveConflict'])->middleware(['permission:libro_digital.curriculum_programs.resolve_conflicts', 'lcd.idempotency'])->name('curriculum.program-imports.conflicts.resolve');
            Route::post('/batches/{batch}/publish', [CurriculumProgramController::class, 'publishBatch'])->middleware(['permission:libro_digital.curriculum_programs.publish', 'lcd.idempotency'])->name('curriculum.program-imports.batches.publish');
            Route::get('/documents/{document}/pages/{page}', [CurriculumProgramController::class, 'documentPage'])->whereNumber('page')->middleware('permission:libro_digital.curriculum_programs.documents.view')->name('curriculum.documents.pages.show');
            Route::get('/documents/{document}/download', [CurriculumProgramController::class, 'downloadDocument'])->middleware('permission:libro_digital.curriculum_programs.documents.view')->name('curriculum.documents.download');
        });
        Route::get('/books/{book}/curriculum-coverage', [CurriculumController::class, 'coverage'])->name('curriculum.coverage');
        Route::get('/sessions/{session}/attendance', [AttendanceController::class, 'show'])->name('attendance.show');
        Route::put('/sessions/{session}/attendance', [AttendanceController::class, 'update'])->middleware('lcd.idempotency')->name('attendance.update');
        Route::post('/sessions/{session}/attendance/complete', [AttendanceController::class, 'complete'])->middleware('lcd.idempotency')->name('attendance.complete');
        Route::get('/books/{book}/attendance/daily', [AttendanceController::class, 'daily'])->name('attendance.daily');
        Route::get('/books/{book}/attendance/monthly', [AttendanceController::class, 'monthly'])->name('attendance.monthly');
        Route::post('/books/{book}/attendance/daily-close', [AttendanceClosureController::class, 'closeDay'])->middleware(['permission:libro_digital.closures.manage', 'lcd.idempotency'])->name('attendance.daily-close');
        Route::post('/books/{book}/attendance/monthly-close', [AttendanceClosureController::class, 'closeMonth'])->middleware(['permission:libro_digital.closures.manage', 'lcd.idempotency'])->name('attendance.monthly-close');
        Route::post('/books/{book}/attendance/reconcile', [AttendanceClosureController::class, 'reconcile'])->middleware(['permission:libro_digital.closures.manage', 'lcd.idempotency'])->name('attendance.reconcile');

        Route::get('/books/{book}/assessments', [AssessmentController::class, 'index'])->name('assessments.index');
        Route::post('/books/{book}/assessments', [AssessmentController::class, 'store'])->middleware(['permission:libro_digital.assessments.manage', 'lcd.idempotency'])->name('assessments.store');
        Route::get('/assessments/{assessment}', [AssessmentController::class, 'show'])->middleware('permission:libro_digital.assessments.manage')->name('assessments.show');
        Route::patch('/assessments/{assessment}', [AssessmentController::class, 'update'])->middleware(['permission:libro_digital.assessments.manage', 'lcd.idempotency'])->name('assessments.update');
        Route::put('/assessments/{assessment}/results', [AssessmentController::class, 'results'])->middleware(['permission:libro_digital.assessments.manage', 'lcd.idempotency'])->name('assessments.results');
        Route::post('/assessments/{assessment}/close', [AssessmentController::class, 'close'])->middleware(['permission:libro_digital.assessments.manage', 'lcd.idempotency'])->name('assessments.close');

        Route::get('/books/{book}/pie', [PieController::class, 'index'])->name('pie.index');
        Route::post('/books/{book}/pie', [PieController::class, 'store'])->middleware(['permission:libro_digital.pie.manage', 'lcd.idempotency'])->name('pie.store');
        Route::get('/pie/{pie}', [PieController::class, 'show'])->name('pie.show');
        Route::patch('/pie/{pie}', [PieController::class, 'update'])->middleware(['permission:libro_digital.pie.manage', 'lcd.idempotency'])->name('pie.update');

        Route::get('/students/{student}/coexistence', [CoexistenceController::class, 'index'])->name('coexistence.index');
        Route::post('/students/{student}/coexistence', [CoexistenceController::class, 'store'])->middleware(['permission:libro_digital.coexistence.manage', 'lcd.idempotency'])->name('coexistence.store');
        Route::get('/coexistence/{coexistence}', [CoexistenceController::class, 'show'])->name('coexistence.show');
        Route::patch('/coexistence/{coexistence}', [CoexistenceController::class, 'update'])->middleware(['permission:libro_digital.coexistence.manage', 'lcd.idempotency'])->name('coexistence.update');

        Route::get('/absence-cases', [AbsenceCaseController::class, 'index'])->middleware('permission:libro_digital.absence.manage')->name('absence-cases.index');
        Route::post('/absence-cases', [AbsenceCaseController::class, 'store'])->middleware(['permission:libro_digital.absence.manage', 'lcd.idempotency'])->name('absence-cases.store');
        Route::post('/absence-cases/{absenceCase}/actions', [AbsenceCaseController::class, 'action'])->middleware(['permission:libro_digital.absence.manage', 'lcd.idempotency'])->name('absence-cases.actions.store');
        Route::post('/absence-cases/{absenceCase}/resolve', [AbsenceCaseController::class, 'resolve'])->middleware(['permission:libro_digital.absence.manage', 'lcd.idempotency'])->name('absence-cases.resolve');

        Route::get('/books/{book}/early-withdrawals', [EarlyWithdrawalController::class, 'index'])->name('early-withdrawals.index');
        Route::post('/books/{book}/early-withdrawals', [EarlyWithdrawalController::class, 'store'])->middleware('lcd.idempotency')->name('early-withdrawals.store');
        Route::post('/early-withdrawals/{withdrawal}/return', [EarlyWithdrawalController::class, 'return'])->middleware('lcd.idempotency')->name('early-withdrawals.return');

        Route::get('/parvularia/books', [ParvulariaController::class, 'indexBooks'])->middleware('permission:libro_digital.parvularia.manage')->name('parvularia.books.index');
        Route::post('/parvularia/books', [ParvulariaController::class, 'storeBook'])->middleware(['permission:libro_digital.parvularia.manage', 'lcd.idempotency'])->name('parvularia.books.store');
        Route::get('/parvularia/books/{book}', [ParvulariaController::class, 'show'])->middleware('permission:libro_digital.parvularia.manage')->name('parvularia.show');
        Route::get('/parvularia/books/{book}/planning', [ParvulariaController::class, 'planning'])->middleware('permission:libro_digital.parvularia.manage')->name('parvularia.planning.index');
        Route::post('/parvularia/books/{book}/planning', [ParvulariaController::class, 'storePlanning'])->middleware(['permission:libro_digital.parvularia.manage', 'lcd.idempotency'])->name('parvularia.planning.store');
        Route::get('/parvularia/books/{book}/evaluations', [ParvulariaController::class, 'evaluations'])->middleware('permission:libro_digital.parvularia.manage')->name('parvularia.evaluations.index');
        Route::post('/parvularia/books/{book}/evaluations', [ParvulariaController::class, 'storeEvaluation'])->middleware(['permission:libro_digital.parvularia.manage', 'lcd.idempotency'])->name('parvularia.evaluations.store');

        Route::get('/amendments', [AmendmentController::class, 'index'])->name('amendments.index');
        Route::post('/amendments', [AmendmentController::class, 'store'])->middleware(['permission:libro_digital.amendments.request', 'lcd.idempotency'])->name('amendments.store');
        Route::post('/amendments/{amendment}/approve', [AmendmentController::class, 'approve'])->middleware(['permission:libro_digital.amendments.review', 'lcd.idempotency'])->name('amendments.approve');
        Route::post('/amendments/{amendment}/reject', [AmendmentController::class, 'reject'])->middleware(['permission:libro_digital.amendments.review', 'lcd.idempotency'])->name('amendments.reject');
        Route::post('/amendments/{amendment}/apply', [AmendmentController::class, 'apply'])->middleware(['permission:libro_digital.amendments.apply', 'lcd.idempotency'])->name('amendments.apply');
        Route::get('/parvularia/books/{book}/late-arrivals', [LateArrivalController::class, 'index'])->middleware('permission:libro_digital.parvularia.manage')->name('parvularia.late-arrivals.index');
        Route::post('/parvularia/books/{book}/late-arrivals', [LateArrivalController::class, 'store'])->middleware(['permission:libro_digital.parvularia.manage', 'lcd.idempotency'])->name('parvularia.late-arrivals.store');

        Route::get('/statistics', StatisticsController::class)->middleware('permission:libro_digital.statistics.view')->name('statistics');
        Route::get('/reports', [ReportController::class, 'index'])->middleware('permission:libro_digital.reports.view')->name('reports.index');
        Route::post('/reports', [ReportController::class, 'store'])->middleware(['permission:libro_digital.reports.export', 'lcd.idempotency'])->name('reports.store');
        Route::get('/reports/history', [ReportController::class, 'history'])->middleware('permission:libro_digital.reports.view')->name('reports.history');
        // The controller permits either report viewers or the export owner. This
        // keeps polling usable for an export-only role without exposing peers' jobs.
        Route::get('/reports/{report}', [ReportController::class, 'show'])->name('reports.show');
        Route::get('/reports/{report}/download', [ReportController::class, 'download'])->name('reports.download');

        Route::get('/ede/versions', [EdeController::class, 'versions'])->name('ede.versions.index');
        Route::get('/ede/mappings', [EdeController::class, 'mappings'])->name('ede.mappings.index');
        Route::post('/ede/import-standard', [EdeController::class, 'importStandard'])->middleware(['permission:libro_digital.ede.manage', 'lcd.idempotency'])->name('ede.standard.import');
        Route::get('/ede/exports', [EdeController::class, 'index'])->name('ede.exports.index');
        Route::post('/ede/exports', [EdeController::class, 'store'])->middleware(['permission:libro_digital.ede.export', 'lcd.idempotency'])->name('ede.exports.store');
        Route::get('/ede/exports/{edeExport}', [EdeController::class, 'show'])->name('ede.exports.show');
        Route::post('/ede/exports/{edeExport}/validate', [EdeController::class, 'validateExport'])->middleware(['permission:libro_digital.ede.validate', 'lcd.idempotency'])->name('ede.exports.validate');
        Route::get('/ede/exports/{edeExport}/report', [EdeController::class, 'report'])->name('ede.exports.report');
        Route::get('/ede/exports/{edeExport}/download', [EdeController::class, 'download'])->middleware('permission:libro_digital.ede.download')->name('ede.exports.download');

        Route::get('/audit', [AuditController::class, 'index'])->middleware('permission:libro_digital.audit.view')->name('audit.index');
        Route::post('/audit/verify', [AuditController::class, 'verify'])->middleware(['permission:libro_digital.audit.verify', 'lcd.idempotency'])->name('audit.verify');
        Route::get('/audit/{entityType}/{entityId}', [AuditController::class, 'entity'])
            ->where('entityType', '[a-z_]+')->whereNumber('entityId')
            ->middleware('permission:libro_digital.audit.view')->name('audit.entity');

        Route::get('/configuration', [ConfigurationController::class, 'show'])->middleware('permission:libro_digital.configuration.manage')->name('configuration.show');
        Route::put('/configuration', [ConfigurationController::class, 'update'])->middleware(['permission:libro_digital.configuration.manage', 'lcd.idempotency'])->name('configuration.update');
    });
