<?php

namespace App\Services;

use App\Models\RiskPrevention\RiskPreventionJointCommittee;
use App\Models\SiteOrganization;
use App\Models\SiteOrganizationMember;
use App\Models\SiteOrganizationRole;
use App\Models\Staff;
use App\Models\StudentEnrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SiteOrganizationManagementService
{
    public function __construct(
        private readonly SiteOrganizationAccessService $accessService,
    ) {}

    public function create(string $type, array $payload, User $user): Model
    {
        $type = $this->accessService->normalizeType($type);

        return DB::transaction(function () use ($type, $payload, $user): Model {
            if ($type === 'joint_committee') {
                $committee = RiskPreventionJointCommittee::query()->create(
                    $this->committeeAttributes($payload, $user, true),
                );
                $this->syncCommitteeMembers($committee, $payload['members'] ?? [], $user);

                return $this->loadCommittee($committee);
            }

            $organization = SiteOrganization::query()->create(
                $this->organizationAttributes($type, $payload, $user, true),
            );
            $this->syncOrganizationMembers($organization, $payload['members'] ?? [], $user);

            return $this->loadOrganization($organization);
        });
    }

    public function update(string $type, int $id, array $payload, User $user): Model
    {
        $type = $this->accessService->normalizeType($type);

        return DB::transaction(function () use ($type, $id, $payload, $user): Model {
            if ($type === 'joint_committee') {
                $committee = RiskPreventionJointCommittee::query()->lockForUpdate()->findOrFail($id);
                $committee->update($this->committeeAttributes($payload, $user, false, $committee));
                $this->syncCommitteeMembers($committee, $payload['members'] ?? [], $user);

                return $this->loadCommittee($committee);
            }

            $organization = SiteOrganization::query()
                ->where('type', $type)
                ->lockForUpdate()
                ->findOrFail($id);
            $organization->update(
                $this->organizationAttributes($type, $payload, $user, false, $organization),
            );
            $this->syncOrganizationMembers($organization, $payload['members'] ?? [], $user);

            return $this->loadOrganization($organization);
        });
    }

    public function archive(string $type, int $id, User $user): Model
    {
        $type = $this->accessService->normalizeType($type);

        return DB::transaction(function () use ($type, $id, $user): Model {
            if ($type === 'joint_committee') {
                $committee = RiskPreventionJointCommittee::query()->lockForUpdate()->findOrFail($id);
                $committee->update([
                    'web_status' => SiteOrganization::STATUS_ARCHIVED,
                    'web_published_at' => null,
                    'updated_by' => $user->id,
                ]);

                return $this->loadCommittee($committee);
            }

            $organization = SiteOrganization::query()
                ->where('type', $type)
                ->lockForUpdate()
                ->findOrFail($id);
            $organization->update([
                'status' => SiteOrganization::STATUS_ARCHIVED,
                'active' => false,
                'published_at' => null,
                'updated_by' => $user->id,
            ]);

            return $this->loadOrganization($organization);
        });
    }

    public function find(string $type, int $id): Model
    {
        $type = $this->accessService->normalizeType($type);

        if ($type === 'joint_committee') {
            return $this->loadCommittee(RiskPreventionJointCommittee::query()->findOrFail($id));
        }

        return $this->loadOrganization(
            SiteOrganization::query()->where('type', $type)->findOrFail($id),
        );
    }

    private function organizationAttributes(
        string $type,
        array $payload,
        User $user,
        bool $creating,
        ?SiteOrganization $organization = null,
    ): array {
        $status = $payload['status'];

        return [
            'type' => $type,
            'year' => (int) $payload['year'],
            'name' => trim($payload['name']),
            'summary' => filled($payload['summary'] ?? null) ? trim($payload['summary']) : null,
            'status' => $status,
            'active' => (bool) $payload['active'],
            'published_at' => $status === SiteOrganization::STATUS_PUBLISHED
                ? ($organization?->published_at ?? now())
                : null,
            'created_by' => $creating ? $user->id : $organization?->created_by,
            'updated_by' => $user->id,
        ];
    }

    private function committeeAttributes(
        array $payload,
        User $user,
        bool $creating,
        ?RiskPreventionJointCommittee $committee = null,
    ): array {
        $status = $payload['status'];

        return [
            'name' => trim($payload['name']),
            'starts_on' => $payload['starts_on'],
            'ends_on' => $payload['ends_on'] ?? null,
            'active' => (bool) $payload['active'],
            'web_summary' => filled($payload['summary'] ?? null) ? trim($payload['summary']) : null,
            'web_status' => $status,
            'web_published_at' => $status === SiteOrganization::STATUS_PUBLISHED
                ? ($committee?->web_published_at ?? now())
                : null,
            'created_by' => $creating ? $user->id : $committee?->created_by,
            'updated_by' => $user->id,
        ];
    }

    private function syncOrganizationMembers(
        SiteOrganization $organization,
        array $members,
        User $user,
    ): void {
        $existingMembers = $organization->members()->get()->keyBy('id');
        $retainedIds = [];
        $roles = SiteOrganizationRole::query()
            ->where('organization_type', $organization->type)
            ->whereIn('id', collect($members)->pluck('role_id')->filter())
            ->get()
            ->keyBy('id');

        foreach ($members as $member) {
            $role = $roles->get((int) ($member['role_id'] ?? 0));
            [$displayName, $detail, $studentId, $staffId] = $this->resolveMemberIdentity(
                $organization,
                $member,
            );
            $authorized = (bool) ($member['public_name_authorized'] ?? false);

            $existing = isset($member['id'])
                ? $existingMembers->get((int) $member['id'])
                : $existingMembers->first(function (SiteOrganizationMember $candidate) use ($studentId, $staffId): bool {
                    return ($studentId && (int) $candidate->student_profile_id === $studentId)
                        || ($staffId && (int) $candidate->staff_id === $staffId);
                });
            $authorizedAt = $authorized
                ? ($existing?->public_name_authorized_at ?? now())
                : null;
            $authorizedBy = $authorized
                ? ($existing?->public_name_authorized_by ?? $user->id)
                : null;
            $attributes = [
                'role_id' => $role?->id,
                'member_kind' => $member['member_kind'],
                'student_profile_id' => $studentId,
                'staff_id' => $staffId,
                'display_name_snapshot' => $displayName,
                'detail_snapshot' => $detail,
                'section' => $role?->section ?? ($member['section'] ?? 'leadership'),
                'sort_order' => (int) ($member['sort_order'] ?? $role?->sort_order ?? 0),
                'public_name_authorized' => $authorized,
                'public_name_authorized_at' => $authorizedAt,
                'public_name_authorized_by' => $authorizedBy,
            ];

            if ($existing) {
                $existing->update($attributes);
                $retainedIds[] = $existing->id;
            } else {
                $retainedIds[] = $organization->members()->create($attributes)->id;
            }
        }

        $organization->members()
            ->when($retainedIds !== [], fn ($query) => $query->whereNotIn('id', $retainedIds))
            ->delete();
    }

    /** @return array{0:string,1:?string,2:?int,3:?int} */
    private function resolveMemberIdentity(SiteOrganization $organization, array $member): array
    {
        if ($member['member_kind'] === SiteOrganizationMember::KIND_EXTERNAL) {
            return [trim($member['display_name']), null, null, null];
        }

        if ($member['member_kind'] === SiteOrganizationMember::KIND_STUDENT) {
            $studentId = (int) ($member['student_profile_id'] ?? $member['student_id']);
            $enrollment = StudentEnrollment::query()
                ->select(['id', 'student_profile_id', 'academic_year_id', 'course_section_id', 'snapshot_course_display_name'])
                ->where('student_profile_id', $studentId)
                ->whereHas('academicYear', fn ($query) => $query->where('year', $organization->year))
                ->whereNotIn('enrollment_status', StudentEnrollment::NON_ROSTER_STATUS_VALUES)
                ->with([
                    'studentProfile:id,first_name,last_name,registered_name',
                    'courseSection:id,display_name',
                ])
                ->firstOrFail();

            return [
                $enrollment->studentProfile->registered_name_resolved,
                $enrollment->courseSection?->display_name ?: $enrollment->snapshot_course_display_name,
                $studentId,
                null,
            ];
        }

        $staff = Staff::query()
            ->select(['id', 'full_name', 'cargo_id'])
            ->where('active', true)
            ->with('cargo:id,name')
            ->findOrFail((int) $member['staff_id']);

        return [$staff->full_name, $staff->cargo?->name, null, $staff->id];
    }

    private function syncCommitteeMembers(
        RiskPreventionJointCommittee $committee,
        array $members,
        User $user,
    ): void {
        $staffIds = collect($members)->pluck('staff_id')->map(fn ($id): int => (int) $id)->all();

        DB::table('prevent_joint_committee_staff')
            ->where('committee_id', $committee->id)
            ->when($staffIds !== [], fn ($query) => $query->whereNotIn('staff_id', $staffIds))
            ->update([
                'active' => false,
                'public_name_authorized' => false,
                'public_name_authorized_at' => null,
                'public_name_authorized_by' => null,
                'updated_at' => now(),
            ]);

        foreach ($members as $member) {
            $staffId = (int) $member['staff_id'];
            Staff::query()->where('active', true)->findOrFail($staffId);
            $authorized = (bool) ($member['public_name_authorized'] ?? false);
            $existing = DB::table('prevent_joint_committee_staff')
                ->where('committee_id', $committee->id)
                ->where('staff_id', $staffId)
                ->first();
            $values = [
                'representation' => $member['representation'],
                'member_role' => $member['member_role'],
                'position_name' => trim($member['position_name']),
                'section' => $member['section'] ?? 'leadership',
                'sort_order' => (int) ($member['sort_order'] ?? 0),
                'joined_on' => $member['joined_on'] ?? null,
                'ended_on' => $member['ended_on'] ?? null,
                'active' => (bool) ($member['active'] ?? true),
                'public_name_authorized' => $authorized,
                'public_name_authorized_at' => $authorized
                    ? ($existing?->public_name_authorized_at ?? now())
                    : null,
                'public_name_authorized_by' => $authorized
                    ? ($existing?->public_name_authorized_by ?? $user->id)
                    : null,
                'updated_at' => now(),
            ];

            if ($existing) {
                DB::table('prevent_joint_committee_staff')->where('id', $existing->id)->update($values);
            } else {
                DB::table('prevent_joint_committee_staff')->insert(array_merge($values, [
                    'committee_id' => $committee->id,
                    'staff_id' => $staffId,
                    'created_at' => now(),
                ]));
            }
        }
    }

    private function loadOrganization(SiteOrganization $organization): SiteOrganization
    {
        return $organization->fresh()->load([
            'members.role:id,organization_type,name,section,sort_order,active',
            'createdBy:id,name',
            'updatedBy:id,name',
        ]);
    }

    private function loadCommittee(
        RiskPreventionJointCommittee $committee,
    ): RiskPreventionJointCommittee {
        return $committee->fresh()->load([
            'staffMembers' => fn ($query) => $query
                ->select(['staff.id', 'staff.full_name', 'staff.cargo_id'])
                ->with('cargo:id,name'),
            'createdBy:id,name',
            'updatedBy:id,name',
        ]);
    }
}
