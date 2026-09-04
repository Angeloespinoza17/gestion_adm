<?php

namespace App\Services;

use App\Models\RiskPrevention\RiskPreventionJointCommittee;
use App\Models\SiteOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class PublicSiteOrganizationResolver
{
    /** @return array<string, mixed>|null */
    public function latest(string $type): ?array
    {
        $type = $this->normalizeType($type);

        if ($type === 'joint_committee') {
            if (! $this->committeeSchemaIsReady()) {
                return null;
            }

            $committee = $this->committeeQuery()->first();

            return $committee ? $this->committee($committee) : null;
        }

        if (! in_array($type, SiteOrganization::TYPES, true) || ! $this->organizationSchemaIsReady()) {
            return null;
        }

        $organization = $this->organizationQuery($type)->first();

        return $organization ? $this->organization($organization) : null;
    }

    /** @return Collection<int, array<string, mixed>> */
    public function allPublished(string $type): Collection
    {
        $type = $this->normalizeType($type);

        if ($type === 'joint_committee') {
            if (! $this->committeeSchemaIsReady()) {
                return collect();
            }

            return $this->committeeQuery()
                ->get()
                ->map(fn (RiskPreventionJointCommittee $committee): array => $this->committee($committee));
        }

        if (! in_array($type, SiteOrganization::TYPES, true) || ! $this->organizationSchemaIsReady()) {
            return collect();
        }

        return $this->organizationQuery($type)
            ->get()
            ->map(fn (SiteOrganization $organization): array => $this->organization($organization));
    }

    /** @return array<string, mixed> */
    private function organization(SiteOrganization $organization): array
    {
        return [
            'type' => $organization->type,
            'id' => $organization->id,
            'name' => $organization->name,
            'year' => $organization->year,
            'starts_on' => null,
            'ends_on' => null,
            'summary' => $organization->summary,
            'published_at' => $organization->published_at?->toISOString(),
            'members' => $organization->publicMembers
                ->map(fn ($member): array => [
                    'display_name' => $member->display_name_snapshot,
                    'position_name' => $member->role?->name,
                    'section' => $this->sectionLabel($member->section),
                    'sort_order' => (int) $member->sort_order,
                    'course_label' => $member->member_kind === 'student'
                        ? $member->detail_snapshot
                        : null,
                ])
                ->values()
                ->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function committee(RiskPreventionJointCommittee $committee): array
    {
        return [
            'type' => 'joint-committee',
            'id' => $committee->id,
            'name' => $committee->name,
            'year' => $committee->starts_on?->year,
            'starts_on' => $committee->starts_on?->toDateString(),
            'ends_on' => $committee->ends_on?->toDateString(),
            'summary' => $committee->web_summary,
            'published_at' => $committee->web_published_at?->toISOString(),
            'members' => $committee->publicStaffMembers
                ->sortBy([
                    fn ($a, $b) => ((int) $a->pivot->sort_order) <=> ((int) $b->pivot->sort_order),
                    fn ($a, $b) => strcmp($a->full_name, $b->full_name),
                ])
                ->map(fn ($staff): array => [
                    'display_name' => $staff->full_name,
                    'position_name' => $staff->pivot->position_name ?: $staff->cargo?->name,
                    'section' => $this->committeeSectionLabel(
                        (string) $staff->pivot->section,
                        (string) $staff->pivot->representation,
                    ),
                    'sort_order' => (int) $staff->pivot->sort_order,
                    'course_label' => null,
                    'representation' => $staff->pivot->representation,
                    'member_role' => $staff->pivot->member_role,
                ])
                ->values()
                ->all(),
        ];
    }

    private function normalizeType(string $type): string
    {
        return str_replace('-', '_', trim(strtolower($type)));
    }

    private function organizationQuery(string $type): Builder
    {
        return SiteOrganization::query()
            ->select([
                'id',
                'type',
                'year',
                'name',
                'summary',
                'published_at',
                'status',
                'active',
            ])
            ->published()
            ->ofType($type)
            ->with([
                'publicMembers' => fn ($query) => $query->select([
                    'id',
                    'site_organization_id',
                    'role_id',
                    'member_kind',
                    'display_name_snapshot',
                    'detail_snapshot',
                    'section',
                    'sort_order',
                ]),
                'publicMembers.role:id,organization_type,name,section,sort_order,active',
            ])
            ->orderByDesc('year')
            ->orderByDesc('id');
    }

    private function committeeQuery(): Builder
    {
        return RiskPreventionJointCommittee::query()
            ->select([
                'id',
                'name',
                'starts_on',
                'ends_on',
                'active',
                'web_summary',
                'web_status',
                'web_published_at',
            ])
            ->publishedOnWebsite()
            ->with([
                'publicStaffMembers' => fn ($query) => $query
                    ->select(['staff.id', 'staff.full_name', 'staff.cargo_id'])
                    ->with('cargo:id,name'),
            ])
            ->orderByDesc('starts_on')
            ->orderByDesc('id');
    }

    private function organizationSchemaIsReady(): bool
    {
        return Schema::hasTable('site_organizations')
            && Schema::hasTable('site_organization_members')
            && Schema::hasTable('site_organization_roles');
    }

    private function committeeSchemaIsReady(): bool
    {
        return Schema::hasTable('prevent_joint_committees')
            && Schema::hasTable('prevent_joint_committee_staff')
            && Schema::hasColumn('prevent_joint_committees', 'web_status')
            && Schema::hasColumn('prevent_joint_committees', 'web_published_at')
            && Schema::hasColumn('prevent_joint_committee_staff', 'public_name_authorized');
    }

    private function sectionLabel(string $section): string
    {
        return match ($section) {
            'leadership' => 'Directiva',
            'advisor' => 'Equipo asesor',
            default => 'Integrantes',
        };
    }

    private function committeeSectionLabel(string $section, string $representation): string
    {
        if ($section === 'leadership') {
            return 'Directiva';
        }

        return $representation === 'empleador'
            ? 'Representantes del empleador'
            : 'Representantes de las personas trabajadoras';
    }
}
