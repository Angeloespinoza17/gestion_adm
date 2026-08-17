<?php

namespace App\Services\LibroDigital;

use App\Exceptions\LibroDigital\LibroDigitalException;
use App\Models\LibroDigital\Book;
use App\Models\LibroDigital\CurriculumCatalogActivation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CurriculumObjectiveScopeService
{
    public function __construct(private readonly BookCurriculumScopeResolver $bookScopes) {}

    /** @param list<int> $objectiveIds */
    public function assertAllowed(Book $book, int $subjectId, array $objectiveIds): void
    {
        $objectiveIds = array_values(array_unique(array_map('intval', $objectiveIds)));
        if ($objectiveIds === []) {
            return;
        }

        foreach ([
            'lcd_learning_objectives',
            'lcd_curriculum_catalog_activations',
            'lcd_subject_curriculum_links',
            'lcd_learning_objective_sources',
            'lcd_curriculum_sources',
        ] as $table) {
            if (! Schema::hasTable($table)) {
                $this->reject($objectiveIds);
            }
        }

        $bookScope = $this->bookScopes->resolve($book, $subjectId);
        if ($bookScope['link_ids'] === []) {
            $this->reject($objectiveIds);
        }

        $on = now()->toDateString();
        $allowedIds = DB::table('lcd_learning_objectives as objectives')
            ->join('lcd_curriculum_catalog_activations as activations', function ($join) use ($book): void {
                $join->on('activations.curriculum_catalog_id', '=', 'objectives.curriculum_catalog_id')
                    ->where('activations.school_id', $book->school_id)
                    ->where('activations.academic_year_id', $book->academic_year_id)
                    ->where('activations.status', CurriculumCatalogActivation::STATUS_ACTIVATED);
            })
            ->join('lcd_subject_curriculum_links as links', function ($join) use ($book, $bookScope, $subjectId, $on): void {
                $join->on('links.curriculum_catalog_id', '=', 'objectives.curriculum_catalog_id')
                    ->whereIn('links.id', $bookScope['link_ids'])
                    ->where('links.school_id', $book->school_id)
                    ->where('links.academic_year_id', $book->academic_year_id)
                    ->where('links.schedule_subject_id', $subjectId)
                    ->where('links.active', true)
                    ->where(function ($dates) use ($on): void {
                        $dates->whereNull('links.valid_from')->orWhereDate('links.valid_from', '<=', $on);
                    })
                    ->where(function ($dates) use ($on): void {
                        $dates->whereNull('links.valid_to')->orWhereDate('links.valid_to', '>=', $on);
                    });
            })
            ->whereIn('objectives.id', $objectiveIds)
            ->where('objectives.active', true)
            ->where('objectives.grade_code', $bookScope['grade_code'])
            ->when(
                $bookScope['curriculum_track'] === null,
                fn ($query) => $query->whereNull('objectives.curriculum_track'),
                fn ($query) => $query->where('objectives.curriculum_track', $bookScope['curriculum_track']),
            )
            ->where(function ($scope) use ($subjectId): void {
                $scope->whereNull('objectives.schedule_subject_id')->orWhere('objectives.schedule_subject_id', $subjectId);
            })
            ->whereColumn('objectives.grade_code', 'links.grade_code')
            ->where(function ($track): void {
                $track->whereColumn('objectives.curriculum_track', 'links.curriculum_track')
                    ->orWhere(function ($nullTrack): void {
                        $nullTrack->whereNull('objectives.curriculum_track')->whereNull('links.curriculum_track');
                    });
            })
            ->whereRaw('(
                select count(*)
                from lcd_learning_objective_sources as canonical_relationships
                where canonical_relationships.learning_objective_id = objectives.id
                  and canonical_relationships.source_role = ?
            ) = 1', ['canonical_text'])
            ->whereExists(function ($canonicalSource): void {
                $canonicalSource->selectRaw('1')
                    ->from('lcd_learning_objective_sources as canonical_relationship')
                    ->join('lcd_curriculum_sources as canonical_source', 'canonical_source.id', '=', 'canonical_relationship.curriculum_source_id')
                    ->whereColumn('canonical_relationship.learning_objective_id', 'objectives.id')
                    ->whereColumn('canonical_source.curriculum_catalog_id', 'objectives.curriculum_catalog_id')
                    ->where('canonical_relationship.source_role', 'canonical_text')
                    ->where('canonical_source.status', 'verified')
                    ->whereNotNull('canonical_source.declared_sha256')
                    ->whereNotNull('canonical_source.verified_sha256')
                    ->whereColumn('canonical_source.declared_sha256', 'canonical_source.verified_sha256');
            })
            ->distinct()->pluck('objectives.id')->map(fn ($id): int => (int) $id)->all();

        $outside = array_values(array_diff($objectiveIds, $allowedIds));
        if ($outside !== []) {
            $this->reject($outside);
        }
    }

    /** @param list<int> $objectiveIds */
    private function reject(array $objectiveIds): never
    {
        throw new LibroDigitalException(
            'Uno o más objetivos no pertenecen a un vínculo curricular activo con fuente canónica verificada.',
            'LCD_CURRICULUM_OBJECTIVE_SCOPE_INVALID',
            422,
            [['field' => 'curriculum_objective_ids', 'objective_ids' => $objectiveIds]],
        );
    }
}
