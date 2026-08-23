<?php

namespace App\Http\Controllers\LibroDigital;

use App\Http\Controllers\Controller;
use App\Models\LibroDigital\Book;
use App\Models\LibroDigital\ClassSession;
use App\Models\LibroDigital\School;
use App\Services\LibroDigital\LibroDigitalAccessContext;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

abstract class LibroDigitalController extends Controller
{
    public function __construct(protected readonly LibroDigitalAccessContext $access) {}

    protected function school(Request $request, ?Book $book = null): School
    {
        return $this->access->resolveSchool($request, $book);
    }

    protected function book(string|int $identifier): Book
    {
        return $this->aggregate(Book::class, $identifier);
    }

    protected function session(string|int $identifier): ClassSession
    {
        return $this->aggregate(ClassSession::class, $identifier);
    }

    /** @template T of Model @param class-string<T> $model @return T */
    protected function aggregate(string $model, string|int $identifier): Model
    {
        return $model::query()
            ->where(function ($query) use ($identifier): void {
                if (ctype_digit((string) $identifier)) {
                    $query->whereKey((int) $identifier)->orWhere('public_id', (string) $identifier);

                    return;
                }

                $query->where('public_id', (string) $identifier);
            })
            ->firstOrFail();
    }

    /** @param array<string, mixed> $data */
    protected function dataResponse(array $data, int $status = 200, ?int $version = null): JsonResponse
    {
        $response = response()->json(['data' => $data], $status);
        if ($version !== null) {
            $response->headers->set('ETag', '"'.$version.'"');
        }

        return $response;
    }

    /** @param array<int, mixed> $data @param array<string, mixed> $meta */
    protected function collectionResponse(array $data, array $meta = []): JsonResponse
    {
        return response()->json(['data' => $data, 'meta' => $meta]);
    }

    protected function statusValue(mixed $status): string
    {
        return $status instanceof BackedEnum ? (string) $status->value : (string) $status;
    }

    /** @return array<string, bool> */
    protected function capabilities(Request $request): array
    {
        $user = $request->user();

        return [
            'can_view_overview' => $user->hasPermission('libro_digital.access'),
            'can_view_books' => $user->hasPermission('libro_digital.books.view'),
            'can_manage_books' => $user->hasPermission('libro_digital.books.manage'),
            'can_view_subjects' => $user->hasPermission('libro_digital.books.view'),
            'can_view_curriculum' => $user->hasPermission('libro_digital.books.view'),
            'can_manage_subject_catalog' => $user->hasPermission('libro_digital.subject_catalog.manage'),
            'can_view_curriculum_imports' => $user->hasPermission('libro_digital.curriculum.import')
                || $user->hasPermission('libro_digital.curriculum.approve')
                || $user->hasPermission('libro_digital.curriculum.activate')
                || $user->hasPermission('libro_digital.audit.view'),
            'can_manage_curriculum_imports' => $user->hasPermission('libro_digital.curriculum.import'),
            'can_approve_curriculum_imports' => $user->hasPermission('libro_digital.curriculum.approve'),
            'can_activate_curriculum_imports' => $user->hasPermission('libro_digital.curriculum.activate'),
            'can_view_curriculum_programs' => $user->hasPermission('libro_digital.curriculum_programs.view'),
            'can_view_curriculum_documents' => $user->hasPermission('libro_digital.curriculum_programs.documents.view'),
            'can_import_curriculum_programs' => $user->hasPermission('libro_digital.curriculum_programs.import'),
            'can_import_curriculum_programs_batch' => $user->hasPermission('libro_digital.curriculum_programs.import_batch'),
            'can_review_curriculum_programs' => $user->hasPermission('libro_digital.curriculum_programs.review'),
            'can_resolve_curriculum_conflicts' => $user->hasPermission('libro_digital.curriculum_programs.resolve_conflicts'),
            'can_publish_curriculum_programs' => $user->hasPermission('libro_digital.curriculum_programs.publish'),
            'can_archive_curriculum_programs' => $user->hasPermission('libro_digital.curriculum_programs.archive'),
            'can_reprocess_curriculum_programs' => $user->hasPermission('libro_digital.curriculum_programs.reprocess'),
            'can_export_curriculum_programs_pdf' => $user->hasPermission('libro_digital.curriculum_programs.export_pdf'),
            'can_view_sessions' => $user->hasPermission('libro_digital.sessions.view'),
            'can_manage_sessions' => $user->hasPermission('libro_digital.sessions.manage'),
            'can_manage_attendance' => $user->hasPermission('libro_digital.attendance.manage'),
            'can_manage_closures' => $user->hasPermission('libro_digital.closures.manage'),
            'can_reconcile_attendance' => $user->hasPermission('libro_digital.closures.manage'),
            'can_manage_lesson' => $user->hasPermission('libro_digital.lesson.manage'),
            'can_sign' => $user->hasPermission('libro_digital.sign'),
            'can_manage_assessments' => $user->hasPermission('libro_digital.assessments.manage'),
            'can_view_pie' => $user->hasPermission('libro_digital.pie.view') || $user->hasPermission('libro_digital.pie.manage'),
            'can_manage_pie' => $user->hasPermission('libro_digital.pie.manage'),
            'can_view_coexistence' => $user->hasPermission('libro_digital.coexistence.view') || $user->hasPermission('libro_digital.coexistence.manage'),
            'can_manage_coexistence' => $user->hasPermission('libro_digital.coexistence.manage'),
            'can_manage_absence' => $user->hasPermission('libro_digital.absence.manage'),
            'can_view_withdrawals' => $user->hasPermission('libro_digital.withdrawals.manage')
                || $user->hasPermission('ver_historial_porteria'),
            'can_manage_withdrawals' => $user->hasPermission('libro_digital.withdrawals.manage')
                || $user->hasPermission('registrar_retiro_porteria'),
            'can_manage_parvularia' => $user->hasPermission('libro_digital.parvularia.manage'),
            'can_request_amendments' => $user->hasPermission('libro_digital.amendments.request'),
            'can_review_amendments' => $user->hasPermission('libro_digital.amendments.review'),
            'can_apply_amendments' => $user->hasPermission('libro_digital.amendments.apply'),
            'can_view_statistics' => $user->hasPermission('libro_digital.statistics.view'),
            'can_view_reports' => $user->hasPermission('libro_digital.reports.view'),
            'can_export_reports' => $user->hasPermission('libro_digital.reports.export'),
            'can_view_compliance' => $user->hasPermission('libro_digital.ede.export')
                || $user->hasPermission('libro_digital.ede.validate')
                || $user->hasPermission('libro_digital.ede.download')
                || $user->hasPermission('libro_digital.audit.view')
                || $user->hasPermission('libro_digital.configuration.manage'),
            'can_export_ede' => $user->hasPermission('libro_digital.ede.export'),
            'can_manage_ede' => $user->hasPermission('libro_digital.ede.manage'),
            'can_validate_ede' => $user->hasPermission('libro_digital.ede.validate'),
            'can_download_ede' => $user->hasPermission('libro_digital.ede.download'),
            'can_view_audit' => $user->hasPermission('libro_digital.audit.view'),
            'can_verify_audit' => $user->hasPermission('libro_digital.audit.verify'),
            'can_manage_configuration' => $user->hasPermission('libro_digital.configuration.manage'),
            'is_super_admin' => $user->isSuperAdmin(),
        ];
    }
}
