<?php

namespace App\Http\Controllers\SocialWork;

use App\Http\Controllers\Controller;
use App\Models\SocialWork\Alert;
use App\Models\SocialWork\JunaebBenefit;
use App\Models\SocialWork\MedicalCertificate;
use App\Models\SocialWork\MedicalService;
use App\Models\SocialWork\ProtectionMeasure;
use App\Models\SocialWork\SocialCase;
use App\Models\SocialWork\StudentProgram;
use App\Models\SocialWork\SupportDevice;
use App\Models\SocialWork\TransportPass;
use App\Models\StudentProfile;
use App\Services\SocialWork\AccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = StudentProfile::query()->select(['id', 'first_name', 'last_name', 'registered_name', 'rut', 'general_status', 'guardian_name', 'guardian_phone', 'is_pie_participant'])
            ->with(['enrollments' => fn ($q) => $q->with(['academicYear:id,name,year', 'courseSection:id,display_name,education_level_id', 'courseSection.educationLevel:id,name'])->latest('academic_year_id'), 'socialWorkCases' => fn ($q) => $q->select(['id', 'primary_student_id', 'risk_level', 'last_activity_at', 'next_milestone', 'responsible_user_id'])->latest('last_activity_at')])
            ->withCount([
                'enrollments',
                'socialWorkCases as active_cases_count' => fn ($q) => $q->whereNotIn('status', ['cerrado', 'anulado']),
                'socialPrograms as active_programs_count' => fn ($q) => $q->where('status', 'vigente'),
                'protectionMeasures as active_protection_measures_count' => fn ($q) => $q->where('status', 'vigente'),
                'socialAlerts as unresolved_alerts_count' => fn ($q) => $q->whereNotIn('status', ['resuelta', 'descartada']),
            ]);
        $search = trim((string) $request->query('search'));
        $query->when($search !== '', fn ($q) => $q->where(fn ($inner) => $inner->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%")->orWhere('registered_name', 'like', "%{$search}%")->orWhere('rut', 'like', "%{$search}%")))
            ->when($request->query('status'), fn ($q, $v) => $q->where('general_status', $v))
            ->when($request->boolean('pie'), fn ($q) => $q->where('is_pie_participant', true))
            ->when($request->query('course_section_id'), fn ($q, $v) => $q->whereHas('enrollments', fn ($e) => $e->where('course_section_id', $v)))
            ->when($request->query('program'), fn ($q, $v) => $q->whereHas('socialPrograms.programType', fn ($p) => $p->where('code', $v)))
            ->when($request->boolean('protection_measure'), fn ($q) => $q->whereHas('protectionMeasures', fn ($m) => $m->where('status', 'vigente')))
            ->when($request->boolean('active_case'), fn ($q) => $q->whereHas('socialWorkCases', fn ($c) => $c->whereNotIn('status', ['cerrado', 'anulado'])))
            ->when($request->query('risk_level'), fn ($q, $v) => $q->whereHas('socialWorkCases', fn ($c) => $c->where('risk_level', $v)));

        $page = $query->orderBy('last_name')->orderBy('first_name')->paginate(min((int) $request->query('per_page', 20), 100));
        $canContact = $request->user()->hasPermission('social_work.confidential.view');
        $page->getCollection()->transform(function ($student) use ($canContact) {
            $student->current_enrollment = $student->enrollments->first();
            $student->latest_social_case = $student->socialWorkCases->first();
            if (! $canContact) unset($student->guardian_phone);
            unset($student->enrollments, $student->socialWorkCases);
            return $student;
        });
        return response()->json($page);
    }

    public function show(Request $request, StudentProfile $student, AccessService $access): JsonResponse
    {
        $cases = $access->applyCaseVisibility(SocialCase::where('primary_student_id', $student->id), $request->user())->with(['responsible:id,name', 'courseSection:id,display_name'])->latest('opened_on')->get();
        $data = ['student' => $student->load(['enrollments.academicYear:id,name,year', 'enrollments.courseSection:id,display_name']), 'cases' => $cases, 'programs' => StudentProgram::with('programType')->where('student_profile_id', $student->id)->get(), 'protection_measures' => ProtectionMeasure::where('student_profile_id', $student->id)->get(), 'junaeb' => JunaebBenefit::with(['benefitType', 'deliveries.items'])->where('student_profile_id', $student->id)->get(), 'transport_passes' => TransportPass::where('student_profile_id', $student->id)->get(), 'medical_services' => MedicalService::where('student_profile_id', $student->id)->get(), 'support_devices' => SupportDevice::where('student_profile_id', $student->id)->get(), 'alerts' => Alert::where('student_profile_id', $student->id)->latest('alerted_at')->get()];
        if ($request->user()->hasPermission('social_work.medical_documents.view')) $data['medical_certificates'] = MedicalCertificate::where('student_profile_id', $student->id)->get();
        if (! $request->user()->hasPermission('social_work.confidential.view')) $data['student']->makeHidden(['guardian_phone', 'guardian_email', 'address', 'health_observations', 'pie_diagnosis']);
        return response()->json(['data' => $data]);
    }

    public function timeline(Request $request, StudentProfile $student): JsonResponse
    {
        $events = collect();
        SocialCase::where('primary_student_id', $student->id)->get()->each(fn ($case) => $events->push(['type' => 'case', 'date' => $case->opened_on, 'title' => "Caso {$case->code}", 'summary' => $case->title, 'confidentiality' => $case->confidentiality, 'id' => $case->id]));
        Alert::where('student_profile_id', $student->id)->get()->each(fn ($alert) => $events->push(['type' => 'alert', 'date' => $alert->alerted_at, 'title' => 'Alerta '.$alert->type, 'summary' => $alert->reason, 'confidentiality' => $alert->confidentiality, 'id' => $alert->id]));
        return response()->json(['data' => $events->sortByDesc('date')->values()]);
    }
}
