<?php

namespace App\Http\Requests;

use App\Models\SiteOrganization;
use App\Models\SiteOrganizationMember;
use App\Models\SiteOrganizationRole;
use App\Models\StudentEnrollment;
use App\Services\SiteOrganizationAccessService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveSiteOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(SiteOrganizationAccessService::class)->canManage(
            $this->user(),
            $this->organizationType(),
        );
    }

    protected function prepareForValidation(): void
    {
        $routeType = $this->route('type');
        $bodyType = $this->input('type');
        $normalizedRouteType = app(SiteOrganizationAccessService::class)->normalizeType($routeType);
        $normalizedBodyType = app(SiteOrganizationAccessService::class)->normalizeType($bodyType);

        $this->merge([
            'type' => $routeType ? $normalizedRouteType : $normalizedBodyType,
            '_type_mismatch' => $routeType && $bodyType && $normalizedRouteType !== $normalizedBodyType,
        ]);
    }

    public function rules(): array
    {
        $type = $this->organizationType();
        $siteOrganizationId = $type === 'joint_committee' ? null : (int) $this->route('id');

        return [
            'type' => ['required', Rule::in(['cgpa', 'cde', 'joint_committee'])],
            '_type_mismatch' => ['declined'],
            'name' => ['required', 'string', 'max:180'],
            'year' => [
                Rule::requiredIf(in_array($type, SiteOrganization::TYPES, true)),
                'nullable',
                'integer',
                'min:2000',
                'max:2100',
                Rule::unique('site_organizations', 'year')
                    ->where(fn ($query) => $query->where('type', $type))
                    ->ignore($siteOrganizationId ?: null),
            ],
            'starts_on' => [Rule::requiredIf($type === 'joint_committee'), 'nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'summary' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::in(SiteOrganization::STATUSES)],
            'active' => ['required', 'boolean'],
            'members' => ['present', 'array', 'max:80'],
            'members.*.id' => ['nullable', 'integer'],
            'members.*.member_kind' => ['required', Rule::in(SiteOrganizationMember::KINDS)],
            'members.*.student_id' => ['nullable', 'integer', 'distinct', 'exists:student_profiles,id'],
            'members.*.student_profile_id' => ['nullable', 'integer', 'distinct', 'exists:student_profiles,id'],
            'members.*.staff_id' => [
                'nullable',
                'integer',
                'distinct',
                Rule::exists('staff', 'id')->where('active', true),
            ],
            'members.*.display_name' => ['nullable', 'string', 'max:180'],
            'members.*.role_id' => [
                Rule::requiredIf(in_array($type, SiteOrganization::TYPES, true)),
                'nullable',
                'integer',
                Rule::exists('site_organization_roles', 'id')
                    ->where('organization_type', $type)
                    ->where('active', true),
            ],
            'members.*.section' => ['nullable', Rule::in(SiteOrganizationRole::SECTIONS)],
            'members.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'members.*.public_name_authorized' => ['required', 'boolean'],
            'members.*.representation' => ['nullable', Rule::in(['trabajadores', 'empleador'])],
            'members.*.member_role' => ['nullable', Rule::in(['titular', 'suplente'])],
            'members.*.position_name' => ['nullable', 'string', 'max:100'],
            'members.*.joined_on' => ['nullable', 'date'],
            'members.*.ended_on' => ['nullable', 'date'],
            'members.*.active' => ['nullable', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $type = $this->organizationType();
            $members = collect($this->input('members', []));

            if ($this->input('status') === SiteOrganization::STATUS_PUBLISHED) {
                if (! $this->boolean('active')) {
                    $validator->errors()->add('active', 'Una versión publicada debe permanecer activa.');
                }
                if ($members->isEmpty()) {
                    $validator->errors()->add('members', 'Debe registrar integrantes antes de publicar.');
                }
                if (! $members->contains(fn (array $member): bool => (bool) ($member['public_name_authorized'] ?? false))) {
                    $validator->errors()->add(
                        'members',
                        'Debe existir al menos un integrante con autorización expresa para mostrar su nombre.',
                    );
                }
                if (
                    $type === 'cde'
                    && ! $members->contains(fn (array $member): bool => ($member['member_kind'] ?? null) === 'student'
                        && (bool) ($member['public_name_authorized'] ?? false))
                ) {
                    $validator->errors()->add(
                        'members',
                        'El CDE publicado debe incluir al menos una estudiante con autorización expresa.',
                    );
                }
            }

            $roleSections = DB::table('site_organization_roles')
                ->whereIn('id', $members->pluck('role_id')->filter()->unique())
                ->pluck('section', 'id');

            foreach ($members as $index => $member) {
                $kind = $member['member_kind'] ?? null;
                $studentId = $member['student_profile_id'] ?? $member['student_id'] ?? null;
                $staffId = $member['staff_id'] ?? null;

                if ($type === 'cgpa' && $kind !== SiteOrganizationMember::KIND_EXTERNAL) {
                    $validator->errors()->add("members.{$index}.member_kind", 'Los integrantes del CGPA se registran como apoderados externos.');
                }
                if ($type === 'cgpa' && blank($member['display_name'] ?? null)) {
                    $validator->errors()->add("members.{$index}.display_name", 'Ingrese el nombre del integrante.');
                }
                if ($type === 'cde' && ! in_array($kind, [SiteOrganizationMember::KIND_STUDENT, SiteOrganizationMember::KIND_STAFF], true)) {
                    $validator->errors()->add("members.{$index}.member_kind", 'El CDE solo admite estudiantes o funcionarios asesores.');
                }
                if ($type === 'cde' && $kind === SiteOrganizationMember::KIND_STAFF
                    && $roleSections->get((int) ($member['role_id'] ?? 0)) !== SiteOrganizationRole::SECTION_ADVISOR) {
                    $validator->errors()->add("members.{$index}.role_id", 'Los funcionarios del CDE deben usar un cargo de asesoría.');
                }
                if ($type === 'cde' && $kind === SiteOrganizationMember::KIND_STUDENT
                    && $roleSections->get((int) ($member['role_id'] ?? 0)) === SiteOrganizationRole::SECTION_ADVISOR) {
                    $validator->errors()->add("members.{$index}.role_id", 'Una estudiante no puede ocupar un cargo de asesoría.');
                }
                if ($type === 'joint_committee' && $kind !== SiteOrganizationMember::KIND_STAFF) {
                    $validator->errors()->add("members.{$index}.member_kind", 'El Comité Paritario solo admite funcionarios registrados.');
                }
                if ($kind === SiteOrganizationMember::KIND_STUDENT && (! $studentId || $staffId)) {
                    $validator->errors()->add("members.{$index}.student_id", 'Seleccione exclusivamente una estudiante.');
                }
                if ($kind === SiteOrganizationMember::KIND_STAFF && (! $staffId || $studentId)) {
                    $validator->errors()->add("members.{$index}.staff_id", 'Seleccione exclusivamente un funcionario.');
                }
                if ($kind === SiteOrganizationMember::KIND_EXTERNAL && ($studentId || $staffId)) {
                    $validator->errors()->add("members.{$index}.member_kind", 'Un integrante externo no puede vincularse a una estudiante o funcionario.');
                }
                if ($type === 'joint_committee') {
                    foreach (['representation', 'member_role', 'position_name'] as $field) {
                        if (blank($member[$field] ?? null)) {
                            $validator->errors()->add("members.{$index}.{$field}", 'Este campo es obligatorio para el Comité Paritario.');
                        }
                    }
                    if (
                        filled($member['joined_on'] ?? null)
                        && filled($member['ended_on'] ?? null)
                        && $member['ended_on'] < $member['joined_on']
                    ) {
                        $validator->errors()->add("members.{$index}.ended_on", 'La fecha de término debe ser posterior al ingreso.');
                    }
                }
            }

            if ($type === 'cde') {
                $this->validateStudentEnrollments($validator, $members);
            }
        }];
    }

    public function organizationType(): string
    {
        return app(SiteOrganizationAccessService::class)->normalizeType(
            $this->route('type') ?: $this->input('type'),
        );
    }

    private function validateStudentEnrollments(Validator $validator, $members): void
    {
        $year = (int) $this->input('year');
        $studentMembers = $members
            ->filter(fn (array $member): bool => ($member['member_kind'] ?? null) === 'student');
        $studentIds = $studentMembers
            ->map(fn (array $member): int => (int) ($member['student_profile_id'] ?? $member['student_id'] ?? 0))
            ->filter()
            ->unique();

        if ($studentIds->isEmpty()) {
            return;
        }

        $enrolledIds = DB::table('student_enrollments')
            ->join('academic_years', 'academic_years.id', '=', 'student_enrollments.academic_year_id')
            ->where('academic_years.year', $year)
            ->whereIn('student_enrollments.student_profile_id', $studentIds)
            ->whereNotIn('student_enrollments.enrollment_status', StudentEnrollment::NON_ROSTER_STATUS_VALUES)
            ->pluck('student_enrollments.student_profile_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        foreach ($studentMembers as $index => $member) {
            $studentId = (int) ($member['student_profile_id'] ?? $member['student_id'] ?? 0);
            if (! in_array($studentId, $enrolledIds, true)) {
                $validator->errors()->add(
                    "members.{$index}.student_id",
                    "La estudiante no posee una matrícula vigente para {$year}.",
                );
            }
        }
    }
}
