<?php

namespace App\Services\LibroDigital;

use App\Exceptions\LibroDigital\LibroDigitalException;
use App\Models\LibroDigital\Book;
use App\Models\LibroDigital\CurriculumCatalogActivation;
use App\Models\LibroDigital\TeachingGroup;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BookCurriculumScopeResolver
{
    private const TRACKS = ['PARVULARIA', 'GENERAL', 'HC', 'TP', 'ARTISTICA'];

    public function __construct(private readonly CurriculumGradeResolver $grades) {}

    /**
     * @return array{grade_code:string,curriculum_track:?string,catalog_ids:list<int>,link_ids:list<int>}
     */
    public function resolve(Book $book, int $subjectId, ?int $catalogId = null): array
    {
        if (! $book->exists || ! $book->course_section_id) {
            $this->invalid('El libro no conserva un curso vigente para resolver su alcance curricular.');
        }

        $book->loadMissing(['courseSection.educationLevel', 'academicYear']);
        $grade = $this->grades->fromEducationLevel($book->courseSection?->educationLevel);
        if ($grade === null) {
            $this->invalid('El nivel educativo del libro no se puede traducir a un grado curricular NT1–4M.');
        }

        $groups = $this->eligibleTeachingGroups($book)
            ->where('schedule_subject_id', $subjectId)
            ->values();
        if ($groups->isEmpty()) {
            $this->invalid('La asignatura no pertenece a un grupo docente del libro.');
        }
        if ($groups->contains(fn ($group): bool => $group->course_section_id !== null
            && (int) $group->course_section_id !== (int) $book->course_section_id)) {
            $this->invalid('Los grupos docentes de la asignatura no pertenecen inequívocamente al curso del libro.');
        }

        $declaredTracks = $groups->map(fn ($group): ?string => $this->track(
            is_array($group->metadata) ? ($group->metadata['curriculum_track'] ?? null) : null,
        ))->filter()->unique()->values();
        $bookTrack = $this->track($book->modality_code);
        if ($bookTrack !== null) {
            $declaredTracks->push($bookTrack);
            $declaredTracks = $declaredTracks->unique()->values();
        }
        if ($declaredTracks->count() > 1) {
            $this->ambiguous($grade, $declaredTracks);
        }
        $declaredTrack = $declaredTracks->first();

        $on = $this->effectiveDate($book);
        $links = DB::table('lcd_subject_curriculum_links as links')
            ->join('lcd_curriculum_catalogs as catalogs', 'catalogs.id', '=', 'links.curriculum_catalog_id')
            ->join('lcd_curriculum_catalog_activations as activations', function ($join) use ($book): void {
                $join->on('activations.curriculum_catalog_id', '=', 'links.curriculum_catalog_id')
                    ->where('activations.school_id', $book->school_id)
                    ->where('activations.academic_year_id', $book->academic_year_id)
                    ->where('activations.status', CurriculumCatalogActivation::STATUS_ACTIVATED);
            })
            ->where('links.school_id', $book->school_id)
            ->where('links.academic_year_id', $book->academic_year_id)
            ->where('links.schedule_subject_id', $subjectId)
            ->where('links.grade_code', $grade)
            ->where('links.active', true)
            ->where('catalogs.active', true)
            ->whereNotNull('catalogs.source_hash')
            ->when($catalogId, fn ($query) => $query->where('links.curriculum_catalog_id', $catalogId))
            ->where(fn ($query) => $query->whereNull('links.valid_from')->orWhereDate('links.valid_from', '<=', $on))
            ->where(fn ($query) => $query->whereNull('links.valid_to')->orWhereDate('links.valid_to', '>=', $on))
            ->select(['links.id', 'links.curriculum_catalog_id', 'links.curriculum_track'])
            ->distinct()->get();

        if ($declaredTrack !== null) {
            $links = $links->where('curriculum_track', $declaredTrack)->values();
        } else {
            $tracks = $links->map(fn ($link): string => $link->curriculum_track === null ? '__NULL__' : (string) $link->curriculum_track)
                ->unique()->values();
            if ($tracks->count() > 1) {
                $this->ambiguous($grade, $tracks->map(fn (string $track): ?string => $track === '__NULL__' ? null : $track));
            }
            $declaredTrack = $tracks->first() === '__NULL__' ? null : $tracks->first();
        }

        if (in_array($grade, ['3M', '4M'], true) && $declaredTrack === null && $links->isNotEmpty()) {
            $this->invalid('El libro de 3° o 4° medio no identifica inequívocamente formación HC, TP o artística.');
        }

        return [
            'grade_code' => $grade,
            'curriculum_track' => $declaredTrack,
            'catalog_ids' => $links->pluck('curriculum_catalog_id')->map(fn ($id): int => (int) $id)->unique()->values()->all(),
            'link_ids' => $links->pluck('id')->map(fn ($id): int => (int) $id)->unique()->values()->all(),
        ];
    }

    /** @return Collection<int, TeachingGroup> */
    public function eligibleTeachingGroups(Book $book): Collection
    {
        $on = $this->effectiveDate($book);

        return $book->teachingGroups()
            ->with('subject')
            ->where('school_id', $book->school_id)
            ->where('academic_year_id', $book->academic_year_id)
            ->where('status', 'active')
            ->whereDate('valid_from', '<=', $on)
            ->where(fn ($query) => $query->whereNull('valid_to')->orWhereDate('valid_to', '>=', $on))
            ->orderBy('schedule_subject_id')
            ->orderBy('id')
            ->get();
    }

    /**
     * Effective scope date is tied to the book's academic period, never to an
     * unbounded wall clock. Current/future years use today clamped to the year;
     * closed books prefer their closure date, also clamped to that year.
     */
    public function effectiveDate(Book $book): string
    {
        $book->loadMissing('academicYear');
        $start = $book->academicYear?->starts_at;
        $end = $book->academicYear?->ends_at;
        if (! $start || ! $end) {
            $this->invalid('El libro no conserva un periodo académico válido para resolver su alcance curricular.');
        }

        $effective = $book->closed_at?->copy()->startOfDay() ?? now('UTC')->startOfDay();
        if ($effective->lt($start)) {
            $effective = $start->copy();
        }
        if ($effective->gt($end)) {
            $effective = $end->copy();
        }

        return $effective->toDateString();
    }

    private function track(mixed $value): ?string
    {
        $track = strtoupper(trim((string) $value));

        return in_array($track, self::TRACKS, true) ? $track : null;
    }

    private function invalid(string $message): never
    {
        throw new LibroDigitalException($message, 'LCD_CURRICULUM_BOOK_SCOPE_INVALID', 422);
    }

    /** @param Collection<int, string|null> $tracks */
    private function ambiguous(string $grade, Collection $tracks): never
    {
        throw new LibroDigitalException(
            'El track curricular del libro es ambiguo; debe declararse explícitamente antes de usar objetivos.',
            'LCD_CURRICULUM_BOOK_TRACK_AMBIGUOUS',
            422,
            [[
                'field' => 'curriculum_track',
                'grade_code' => $grade,
                'candidate_tracks' => $tracks->map(fn ($track) => $track ?? 'NULL')->values()->all(),
            ]],
        );
    }
}
