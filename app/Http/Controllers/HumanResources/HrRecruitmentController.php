<?php

namespace App\Http\Controllers\HumanResources;

use App\Http\Controllers\Controller;
use App\Models\Cargo;
use App\Models\HumanResources\HrCvBankEntry;
use App\Models\HumanResources\HrJobProfile;
use App\Models\HumanResources\HrPsycholaborInterview;
use App\Models\HumanResources\HrRecruitmentApplication;
use App\Models\HumanResources\HrRecruitmentVacancy;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class HrRecruitmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeView($request);
        $confidential = $this->can($request, 'rrhh.psicolaborales.confidencial');
        $candidates = HrCvBankEntry::query()
            ->withCount('applications')
            ->with(['applications' => fn ($query) => $query->with('vacancy:id,title')->latest()->limit(3)])
            ->latest('updated_at')
            ->limit(500)
            ->get();
        $vacancies = HrRecruitmentVacancy::query()
            ->with(['jobProfile:id,title,code', 'cargo:id,name', 'responsible:id,name'])
            ->withCount('applications')
            ->latest('id')
            ->get();
        $applications = HrRecruitmentApplication::query()
            ->with(['candidate:id,full_name,email,phone,desired_position,status', 'vacancy:id,title,status', 'interviews:id,application_id,result,status'])
            ->latest('id')
            ->limit(750)
            ->get();
        $interviews = HrPsycholaborInterview::query()
            ->with(['application.candidate:id,full_name,email', 'application.vacancy:id,title', 'interviewer:id,name'])
            ->latest('id')
            ->limit(750)
            ->get()
            ->map(function (HrPsycholaborInterview $interview) use ($confidential): array {
                $data = $interview->toArray();
                $data['report_available'] = (bool) $interview->report_path;
                if ($confidential) {
                    $data['confidential_notes'] = $interview->getRawOriginal('confidential_notes');
                }

                return $data;
            });

        return response()->json(['data' => [
            'summary' => [
                'candidates' => $candidates->count(),
                'active_vacancies' => $vacancies->whereIn('status', ['abierta', 'preseleccion', 'entrevistas', 'decision'])->count(),
                'applications' => $applications->count(),
                'interviews' => $interviews->count(),
                'hired' => $applications->where('stage', 'contratado')->count(),
                'reconsiderable' => $applications->whereIn('reconsideration', ['si', 'condicional'])->count(),
            ],
            'candidates' => $candidates,
            'vacancies' => $vacancies,
            'applications' => $applications,
            'interviews' => $interviews,
            'job_profiles' => HrJobProfile::query()->with('cargo:id,name')->orderBy('title')->get(),
            'cargos' => Cargo::query()->select('id', 'name')->where('active', true)->orderBy('name')->get(),
            'users' => User::query()->select('id', 'name')->where('active', true)->orderBy('name')->get(),
            'catalogs' => $this->catalogs(),
            'capabilities' => [
                'manage' => $this->can($request, 'rrhh.seleccion.gestionar'),
                'import' => $this->can($request, 'rrhh.seleccion.importar'),
                'confidential' => $confidential,
            ],
        ]]);
    }

    public function storeCandidate(Request $request): JsonResponse
    {
        $this->authorizeManage($request);
        $payload = $this->candidatePayload($request);
        $candidate = HrCvBankEntry::create($payload + [
            'normalized_name' => $this->normalizeName($payload['full_name']),
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return response()->json(['message' => 'Candidato agregado al banco de talento.', 'data' => $candidate], 201);
    }

    public function updateCandidate(Request $request, HrCvBankEntry $candidate): JsonResponse
    {
        $this->authorizeManage($request);
        $payload = $this->candidatePayload($request);
        $candidate->update($payload + [
            'normalized_name' => $this->normalizeName($payload['full_name']),
            'updated_by' => $request->user()->id,
        ]);

        return response()->json(['message' => 'Candidato actualizado.', 'data' => $candidate]);
    }

    public function uploadCv(Request $request, HrCvBankEntry $candidate): JsonResponse
    {
        $this->authorizeManage($request);
        $payload = $request->validate(['file' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:10240']]);
        if ($candidate->cv_path) {
            Storage::disk('local')->delete($candidate->cv_path);
        }
        $file = $payload['file'];
        $path = $file->store('human-resources/cvs', 'local');
        $candidate->update([
            'cv_path' => $path,
            'cv_file_name' => $file->getClientOriginalName(),
            'cv_mime_type' => $file->getMimeType(),
            'cv_file_size' => $file->getSize(),
            'updated_by' => $request->user()->id,
        ]);

        return response()->json(['message' => 'Currículum protegido y adjuntado.', 'data' => $candidate]);
    }

    public function downloadCv(Request $request, HrCvBankEntry $candidate): BinaryFileResponse
    {
        $this->authorizeView($request);
        abort_unless($candidate->cv_path && Storage::disk('local')->exists($candidate->cv_path), 404);

        return response()->download(Storage::disk('local')->path($candidate->cv_path), $candidate->cv_file_name ?: basename($candidate->cv_path));
    }

    public function storeVacancy(Request $request): JsonResponse
    {
        $this->authorizeManage($request);
        $vacancy = HrRecruitmentVacancy::create($this->vacancyPayload($request) + [
            'responsible_user_id' => $request->input('responsible_user_id') ?: $request->user()->id,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return response()->json(['message' => 'Vacante creada.', 'data' => $vacancy], 201);
    }

    public function updateVacancy(Request $request, HrRecruitmentVacancy $vacancy): JsonResponse
    {
        $this->authorizeManage($request);
        $vacancy->update($this->vacancyPayload($request) + ['updated_by' => $request->user()->id]);

        return response()->json(['message' => 'Vacante actualizada.', 'data' => $vacancy]);
    }

    public function storeApplication(Request $request): JsonResponse
    {
        $this->authorizeManage($request);
        $payload = $this->applicationPayload($request);
        $application = HrRecruitmentApplication::create($payload + [
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return response()->json(['message' => 'Postulación registrada.', 'data' => $application], 201);
    }

    public function updateApplication(Request $request, HrRecruitmentApplication $application): JsonResponse
    {
        $this->authorizeManage($request);
        $application->update($this->applicationPayload($request) + ['updated_by' => $request->user()->id]);

        return response()->json(['message' => 'Etapa de postulación actualizada.', 'data' => $application]);
    }

    public function storeInterview(Request $request): JsonResponse
    {
        $this->authorizeManage($request);
        $payload = $this->interviewPayload($request);
        $interview = HrPsycholaborInterview::create($payload + [
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return response()->json(['message' => 'Entrevista psicolaboral registrada.', 'data' => $interview], 201);
    }

    public function updateInterview(Request $request, HrPsycholaborInterview $interview): JsonResponse
    {
        $this->authorizeManage($request);
        $interview->update($this->interviewPayload($request) + ['updated_by' => $request->user()->id]);

        return response()->json(['message' => 'Entrevista actualizada.', 'data' => $interview]);
    }

    public function uploadReport(Request $request, HrPsycholaborInterview $interview): JsonResponse
    {
        $this->authorizeConfidential($request);
        $payload = $request->validate(['file' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:15360']]);
        if ($interview->report_path) {
            Storage::disk('local')->delete($interview->report_path);
        }
        $file = $payload['file'];
        $path = $file->store('human-resources/psycholabor-reports', 'local');
        $interview->update([
            'report_path' => $path,
            'report_file_name' => $file->getClientOriginalName(),
            'report_mime_type' => $file->getMimeType(),
            'report_file_size' => $file->getSize(),
            'updated_by' => $request->user()->id,
        ]);

        return response()->json(['message' => 'Informe confidencial adjuntado.']);
    }

    public function downloadReport(Request $request, HrPsycholaborInterview $interview): BinaryFileResponse
    {
        $this->authorizeConfidential($request);
        abort_unless($interview->report_path && Storage::disk('local')->exists($interview->report_path), 404);

        return response()->download(Storage::disk('local')->path($interview->report_path), $interview->report_file_name ?: basename($interview->report_path));
    }

    public function storeJobProfile(Request $request): JsonResponse
    {
        $this->authorizeManage($request);
        $payload = $this->jobProfilePayload($request);
        $profile = HrJobProfile::create($payload + [
            'code' => $payload['code'] ?: 'PC-'.mb_strtoupper(Str::random(8)),
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return response()->json(['message' => 'Perfil de cargo creado.', 'data' => $profile], 201);
    }

    public function updateJobProfile(Request $request, HrJobProfile $profile): JsonResponse
    {
        $this->authorizeManage($request);
        $payload = $this->jobProfilePayload($request, $profile->id);
        unset($payload['code']);
        $profile->update($payload + ['updated_by' => $request->user()->id]);

        return response()->json(['message' => 'Perfil de cargo actualizado.', 'data' => $profile]);
    }

    private function candidatePayload(Request $request): array
    {
        return $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'rut' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:80'],
            'source' => ['nullable', 'string', 'max:120'],
            'desired_position' => ['nullable', 'string', 'max:160'],
            'specialty' => ['nullable', 'string', 'max:160'],
            'experience_years' => ['nullable', 'numeric', 'min:0', 'max:80'],
            'availability' => ['nullable', 'string', 'max:120'],
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'status' => ['required', 'in:postulante,banco_talento,preseleccionado,no_disponible,contratado,descartado'],
            'notes' => ['nullable', 'string', 'max:4000'],
        ]);
    }

    private function vacancyPayload(Request $request): array
    {
        return $request->validate([
            'job_profile_id' => ['nullable', 'integer', 'exists:hr_job_profiles,id'],
            'cargo_id' => ['nullable', 'integer', 'exists:cargos,id'],
            'responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'title' => ['required', 'string', 'max:255'],
            'area' => ['nullable', 'string', 'max:140'],
            'vacancy_count' => ['required', 'integer', 'min:1', 'max:100'],
            'employment_type' => ['nullable', 'string', 'max:80'],
            'weekly_hours' => ['nullable', 'numeric', 'min:0', 'max:60'],
            'reason' => ['nullable', 'string', 'max:160'],
            'opened_on' => ['nullable', 'date'],
            'target_start_on' => ['nullable', 'date'],
            'closes_on' => ['nullable', 'date'],
            'status' => ['required', 'in:borrador,abierta,preseleccion,entrevistas,decision,cubierta,cerrada,cancelada'],
            'description' => ['nullable', 'string', 'max:5000'],
            'requirements' => ['nullable', 'string', 'max:5000'],
        ]);
    }

    private function applicationPayload(Request $request): array
    {
        return $request->validate([
            'vacancy_id' => ['nullable', 'integer', 'exists:hr_recruitment_vacancies,id'],
            'cv_bank_entry_id' => ['required', 'integer', 'exists:hr_cv_bank_entries,id'],
            'stage' => ['required', 'in:cv_recibido,filtro_inicial,preseleccionado,entrevista_psicolaboral,decision,contratado,no_seleccionado,retirado,banco_talento'],
            'source' => ['nullable', 'string', 'max:120'],
            'applied_on' => ['nullable', 'date'],
            'score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'reconsideration' => ['nullable', 'in:si,no,condicional'],
            'reconsideration_notes' => ['nullable', 'string', 'max:4000'],
            'outcome' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:4000'],
        ]);
    }

    private function interviewPayload(Request $request): array
    {
        $rules = [
            'application_id' => ['required', 'integer', 'exists:hr_recruitment_applications,id'],
            'interviewer_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'scheduled_at' => ['nullable', 'date'],
            'completed_at' => ['nullable', 'date'],
            'interviewer_name' => ['nullable', 'string', 'max:255'],
            'result' => ['nullable', 'in:apto,apto_con_observaciones,no_apto,pendiente'],
            'induction_required' => ['boolean'],
            'reconsideration' => ['nullable', 'in:si,no,condicional'],
            'considerations' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', 'in:programada,realizada,pendiente_informe,completada,cancelada'],
            'source' => ['nullable', 'string', 'max:120'],
        ];
        if ($this->can($request, 'rrhh.psicolaborales.confidencial')) {
            $rules['confidential_notes'] = ['nullable', 'string', 'max:10000'];
        }

        return $request->validate($rules);
    }

    private function jobProfilePayload(Request $request, ?int $profileId = null): array
    {
        return $request->validate([
            'cargo_id' => ['nullable', 'integer', 'exists:cargos,id'],
            'code' => ['nullable', 'string', 'max:80', 'unique:hr_job_profiles,code'.($profileId ? ','.$profileId : '')],
            'title' => ['required', 'string', 'max:255'],
            'area' => ['nullable', 'string', 'max:140'],
            'purpose' => ['nullable', 'string', 'max:5000'],
            'responsibilities' => ['nullable', 'array'],
            'responsibilities.*' => ['string', 'max:1000'],
            'requirements' => ['nullable', 'array'],
            'requirements.*' => ['string', 'max:1000'],
            'competencies' => ['nullable', 'array'],
            'competencies.*' => ['string', 'max:1000'],
            'version' => ['required', 'string', 'max:40'],
            'status' => ['required', 'in:borrador,vigente,obsoleto'],
            'notes' => ['nullable', 'string', 'max:4000'],
        ]);
    }

    private function catalogs(): array
    {
        return [
            'candidate_statuses' => ['postulante', 'banco_talento', 'preseleccionado', 'no_disponible', 'contratado', 'descartado'],
            'vacancy_statuses' => ['borrador', 'abierta', 'preseleccion', 'entrevistas', 'decision', 'cubierta', 'cerrada', 'cancelada'],
            'application_stages' => ['cv_recibido', 'filtro_inicial', 'preseleccionado', 'entrevista_psicolaboral', 'decision', 'contratado', 'no_seleccionado', 'retirado', 'banco_talento'],
            'interview_results' => ['apto', 'apto_con_observaciones', 'no_apto', 'pendiente'],
        ];
    }

    private function authorizeView(Request $request): void
    {
        abort_unless($this->can($request, 'rrhh.seleccion.ver'), 403);
    }

    private function authorizeManage(Request $request): void
    {
        abort_unless($this->can($request, 'rrhh.seleccion.gestionar'), 403);
    }

    private function authorizeConfidential(Request $request): void
    {
        abort_unless($this->can($request, 'rrhh.psicolaborales.confidencial'), 403);
    }

    private function can(Request $request, string $permission): bool
    {
        return $request->user()->isSuperAdmin() || $request->user()->hasPermission($permission);
    }

    private function normalizeName(string $value): string
    {
        return Str::of($value)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->squish()->toString();
    }
}
