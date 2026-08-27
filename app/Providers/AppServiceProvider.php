<?php

namespace App\Providers;

use App\Contracts\LibroDigital\BulkTeacherIdentityVerifier;
use App\Contracts\LibroDigital\SigeIntegrationGateway;
use App\Contracts\LibroDigital\TeacherIdentityVerifier;
use App\Contracts\LibroDigital\Curriculum\CurriculumDocumentClassifierInterface;
use App\Contracts\LibroDigital\Curriculum\PdfOcrExtractorInterface;
use App\Contracts\LibroDigital\Curriculum\PdfTextExtractorInterface;
use App\Contracts\PedagogicalManagement\PdfTextExtractorInterface as PedagogicalPdfTextExtractorInterface;
use App\Contracts\PedagogicalManagement\PedagogicalInstrumentReviewerInterface;
use App\Services\Attendance\AttendanceParserRegistry;
use App\Services\Attendance\LirmiAttendancePdfParser;
use App\Services\LibroDigital\Identity\DisabledIdentityVerifier;
use App\Services\LibroDigital\Identity\FakeIdentityVerifier;
use App\Services\LibroDigital\Identity\MineducBulkIdentityVerifier;
use App\Services\LibroDigital\Identity\MineducTransactionalIdentityVerifier;
use App\Services\LibroDigital\Sige\DisabledSigeGateway;
use App\Services\LibroDigital\Sige\SigeManualReconciliationGateway;
use App\Services\LibroDigital\Curriculum\CurricularBasesDocumentParser;
use App\Services\LibroDigital\Curriculum\CurriculumDocumentParserRegistry;
use App\Services\LibroDigital\Curriculum\CurriculumPrioritizationParser;
use App\Services\LibroDigital\Curriculum\GenericCurriculumDocumentParser;
use App\Services\LibroDigital\Curriculum\HeuristicCurriculumDocumentClassifier;
use App\Services\LibroDigital\Curriculum\ProgramStudyDocumentParser;
use App\Services\LibroDigital\Curriculum\SmalotPdfTextExtractor;
use App\Services\LibroDigital\Curriculum\StudyPlanDocumentParser;
use App\Services\LibroDigital\Curriculum\UnavailablePdfOcrExtractor;
use App\Services\PedagogicalManagement\DeterministicPedagogicalInstrumentReviewer;
use App\Services\PedagogicalManagement\SmalotPedagogicalPdfTextExtractor;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(PdfTextExtractorInterface::class, SmalotPdfTextExtractor::class);
        $this->app->singleton(PedagogicalPdfTextExtractorInterface::class, SmalotPedagogicalPdfTextExtractor::class);
        $this->app->singleton(PedagogicalInstrumentReviewerInterface::class, DeterministicPedagogicalInstrumentReviewer::class);
        $this->app->singleton(PdfOcrExtractorInterface::class, UnavailablePdfOcrExtractor::class);
        $this->app->singleton(CurriculumDocumentClassifierInterface::class, HeuristicCurriculumDocumentClassifier::class);
        $this->app->singleton(CurriculumDocumentParserRegistry::class, fn ($app) => new CurriculumDocumentParserRegistry([
            $app->make(ProgramStudyDocumentParser::class),
            $app->make(CurricularBasesDocumentParser::class),
            $app->make(StudyPlanDocumentParser::class),
            $app->make(CurriculumPrioritizationParser::class),
            $app->make(GenericCurriculumDocumentParser::class),
        ]));

        $this->app->singleton(AttendanceParserRegistry::class, fn ($app) => new AttendanceParserRegistry([
            $app->make(LirmiAttendancePdfParser::class),
        ]));

        $this->app->singleton(TeacherIdentityVerifier::class, function ($app): TeacherIdentityVerifier {
            if ($app->environment('testing')) {
                return $app->make(FakeIdentityVerifier::class);
            }

            return match ((string) config('libro_digital.identity_verifier.driver', 'disabled')) {
                'mineduc_transactional' => $app->make(MineducTransactionalIdentityVerifier::class),
                default => $app->make(DisabledIdentityVerifier::class),
            };
        });
        $this->app->singleton(BulkTeacherIdentityVerifier::class, MineducBulkIdentityVerifier::class);
        $this->app->singleton(SigeIntegrationGateway::class, function ($app): SigeIntegrationGateway {
            return match ((string) config('libro_digital.sige.driver', 'disabled')) {
                'manual' => $app->make(SigeManualReconciliationGateway::class),
                default => $app->make(DisabledSigeGateway::class),
            };
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Schema::defaultStringLength(191);

        // cPanel/Apache a veces elimina el header `Authorization` antes de PHP.
        // Esto permite autenticar Sanctum leyendo el token desde headers alternativos.
        Sanctum::getAccessTokenFromRequestUsing(function ($request) {
            $raw = $request->headers->get('X-Api-Token')
                ?: $request->headers->get('X-Authorization')
                ?: $request->cookie('cnsc_token');

            if (!is_string($raw) || $raw === '') {
                return $request->bearerToken();
            }

            $raw = rawurldecode($raw);

            if (str_starts_with($raw, 'Bearer ')) {
                return substr($raw, 7);
            }

            return $raw;
        });
    }
}
