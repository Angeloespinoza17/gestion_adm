<?php

namespace App\Http\Controllers;

use App\Services\PublicSiteOrganizationResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PublicCommunityOrganizationController extends Controller
{
    public function __construct(
        private readonly PublicSiteOrganizationResolver $organizationResolver,
    ) {}

    public function cgpa(): View
    {
        return $this->show('cgpa');
    }

    public function cde(): View
    {
        return $this->show('cde');
    }

    public function jointCommittee(): View
    {
        return $this->show('joint_committee');
    }

    private function show(string $type): View
    {
        $presentation = $this->presentation($type);
        $organization = $this->organizationResolver->latest($type);

        return view('public.pages.community-organization', [
            'presentation' => $presentation,
            'period' => $organization ? $this->period($organization, $presentation) : null,
        ]);
    }

    /**
     * Restricts the view contract to public-safe fields. The resolver already
     * enforces publication and consent; this projection prevents an Eloquent
     * model or a private attribute from being passed to Blade accidentally.
     *
     * @param  array<string, mixed>  $organization
     * @param  array<string, string>  $presentation
     * @return array<string, mixed>
     */
    private function period(array $organization, array $presentation): array
    {
        $members = collect($organization['members'] ?? [])
            ->map(fn (array $member): array => [
                'name' => trim((string) ($member['display_name'] ?? '')),
                'position' => trim((string) ($member['position_name'] ?? '')) ?: 'Integrante',
                'section' => trim((string) ($member['section'] ?? '')) ?: $presentation['default_group'],
                'course' => $presentation['type'] === 'cde' && filled($member['course_label'] ?? null)
                    ? trim((string) $member['course_label'])
                    : null,
                'initials' => $this->initials((string) ($member['display_name'] ?? '')),
            ])
            ->filter(fn (array $member): bool => $member['name'] !== '')
            ->values();

        return [
            'display_name' => filled($organization['name'] ?? null)
                ? trim((string) $organization['name'])
                : $this->fallbackPeriodName($organization, $presentation),
            'description' => filled($organization['summary'] ?? null)
                ? trim((string) $organization['summary'])
                : null,
            'period_value' => $this->periodValue($organization, $presentation),
            'date_range' => $this->dateRange(
                $organization['starts_on'] ?? null,
                $organization['ends_on'] ?? null,
            ),
            'public_members_count' => $members->count(),
            'groups' => $this->groups($members, $presentation),
        ];
    }

    /**
     * @param  Collection<int, array{name:string,position:string,section:string,course:?string,initials:string}>  $members
     * @param  array<string, string>  $presentation
     * @return Collection<int, array{label:string,eyebrow:string,icon:string,members:Collection<int, array<string, mixed>>}>
     */
    private function groups(Collection $members, array $presentation): Collection
    {
        return $members
            ->groupBy('section')
            ->map(fn (Collection $groupMembers, string $section): array => [
                'label' => $section,
                'eyebrow' => $presentation['group_eyebrow'],
                'icon' => $this->groupIcon($section, $presentation['type']),
                'members' => $groupMembers->values(),
            ])
            ->values();
    }

    /** @param array<string, mixed> $organization */
    private function fallbackPeriodName(array $organization, array $presentation): string
    {
        $year = (int) ($organization['year'] ?? 0);

        return $year > 0
            ? $presentation['acronym'].' '.$year
            : $presentation['title'];
    }

    /**
     * @param  array<string, mixed>  $organization
     * @param  array<string, string>  $presentation
     */
    private function periodValue(array $organization, array $presentation): string
    {
        if ($presentation['type'] !== 'joint-committee') {
            return (string) ($organization['year'] ?? '—');
        }

        if (filled($organization['name'] ?? null)) {
            return trim((string) $organization['name']);
        }

        return $this->dateRange(
            $organization['starts_on'] ?? null,
            $organization['ends_on'] ?? null,
        ) ?: 'Vigente';
    }

    private function dateRange(mixed $startsOn, mixed $endsOn): ?string
    {
        if (! $startsOn && ! $endsOn) {
            return null;
        }

        $start = $startsOn ? Carbon::parse($startsOn)->locale('es') : null;
        $end = $endsOn ? Carbon::parse($endsOn)->locale('es') : null;

        if ($start && $end) {
            return $start->translatedFormat('j F Y').' — '.$end->translatedFormat('j F Y');
        }

        return $start
            ? 'Desde '.$start->translatedFormat('j F Y')
            : 'Hasta '.$end?->translatedFormat('j F Y');
    }

    private function initials(string $name): string
    {
        $initials = collect(preg_split('/\s+/u', trim($name)) ?: [])
            ->filter()
            ->take(2)
            ->map(fn (string $part): string => Str::upper(Str::substr($part, 0, 1)))
            ->implode('');

        return $initials ?: 'CNSC';
    }

    private function groupIcon(string $section, string $type): string
    {
        $key = Str::lower(Str::ascii($section));

        return match (true) {
            str_contains($key, 'directiva') => 'bi-diagram-3',
            str_contains($key, 'asesor') => 'bi-person-badge',
            str_contains($key, 'estudiante') => 'bi-mortarboard',
            str_contains($key, 'empleador') => 'bi-building',
            str_contains($key, 'trabajador') => 'bi-people',
            $type === 'joint-committee' => 'bi-shield-check',
            default => 'bi-people',
        };
    }

    /** @return array<string, string> */
    private function presentation(string $type): array
    {
        return match ($type) {
            'cgpa' => [
                'type' => 'cgpa',
                'acronym' => 'CGPA',
                'title' => 'Centro General de Padres y Apoderados',
                'meta_description' => 'Directiva pública vigente del Centro General de Padres y Apoderados del Colegio Nuestra Señora del Carmen de Valdivia.',
                'icon' => 'bi-house-heart',
                'eyebrow' => 'Familias que participan',
                'lead' => 'Un espacio de representación y colaboración que fortalece el vínculo entre las familias y nuestra comunidad educativa.',
                'identity_label' => 'Familia · participación · comunidad',
                'intro_title' => 'Una comunidad que acompaña y construye',
                'intro' => 'El Centro General de Padres y Apoderados canaliza la participación organizada de las familias y promueve iniciativas que contribuyen al proyecto educativo y al bienestar de las estudiantes.',
                'period_eyebrow' => 'Directiva publicada',
                'period_label' => 'Año',
                'period_description' => 'Conoce la composición pública de la directiva correspondiente al período vigente.',
                'empty_message' => 'La directiva pública del CGPA aún no ha sido publicada para el período vigente.',
                'default_group' => 'Directiva',
                'group_eyebrow' => 'Estructura del CGPA',
            ],
            'cde' => [
                'type' => 'cde',
                'acronym' => 'CDE',
                'title' => 'Centro de Estudiantes',
                'meta_description' => 'Directiva pública vigente del Centro de Estudiantes del Colegio Nuestra Señora del Carmen de Valdivia.',
                'icon' => 'bi-mortarboard',
                'eyebrow' => 'Participación estudiantil',
                'lead' => 'La voz organizada de las estudiantes para proponer, representar y participar activamente en la vida del colegio.',
                'identity_label' => 'Voz · liderazgo · servicio',
                'intro_title' => 'Liderazgo al servicio de la comunidad',
                'intro' => 'El Centro de Estudiantes impulsa una participación responsable, dialogante y comprometida. Su directiva y asesoría acompañan iniciativas que enriquecen la experiencia escolar.',
                'period_eyebrow' => 'Directiva publicada',
                'period_label' => 'Año',
                'period_description' => 'Conoce la composición pública de la directiva y su equipo asesor para el período vigente.',
                'empty_message' => 'La directiva pública del CDE aún no ha sido publicada para el período vigente.',
                'default_group' => 'Directiva estudiantil',
                'group_eyebrow' => 'Organización del CDE',
            ],
            'joint_committee' => [
                'type' => 'joint-committee',
                'acronym' => 'CPHS',
                'title' => 'Comité Paritario de Higiene y Seguridad',
                'meta_description' => 'Composición pública vigente del Comité Paritario de Higiene y Seguridad del Colegio Nuestra Señora del Carmen de Valdivia.',
                'icon' => 'bi-shield-check',
                'eyebrow' => 'Prevención y cuidado',
                'lead' => 'Una instancia bipartita que promueve condiciones de trabajo seguras y una cultura preventiva para toda la comunidad.',
                'identity_label' => 'Prevención · seguridad · cuidado',
                'intro_title' => 'Compromiso compartido con la seguridad',
                'intro' => 'El Comité Paritario reúne representantes de las personas trabajadoras y del empleador para colaborar en la prevención de riesgos y el cuidado de los espacios de trabajo.',
                'period_eyebrow' => 'Versión publicada',
                'period_label' => 'Versión del comité',
                'period_description' => 'Conoce la composición pública correspondiente a la versión vigente del Comité Paritario.',
                'empty_message' => 'La composición pública del Comité Paritario aún no ha sido publicada para la versión vigente.',
                'default_group' => 'Integrantes',
                'group_eyebrow' => 'Representación paritaria',
            ],
            default => abort(404),
        };
    }
}
