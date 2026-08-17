<?php

namespace App\Services\LibroDigital;

use App\Enums\LibroDigital\BookStatus;
use App\Exceptions\LibroDigital\LibroDigitalException;
use App\Models\CourseSection;
use App\Models\LibroDigital\Book;
use App\Models\LibroDigital\School;

class CurriculumCourseScopeResolver
{
    public function __construct(private readonly CurriculumGradeResolver $grades) {}

    /**
     * Derive the canonical curriculum grade/level from an operational course.
     * course_sections has no school_id, so an existing non-archived Libro
     * Digital is the fail-closed tenant bridge for the selected school/year.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function apply(School $school, int $academicYearId, array $filters, ?Book $book = null): array
    {
        $courseId = isset($filters['course_section_id']) && filled($filters['course_section_id'])
            ? (int) $filters['course_section_id']
            : (int) ($book?->course_section_id ?? 0);
        if ($courseId < 1) {
            return $filters;
        }

        $course = CourseSection::query()
            ->with('educationLevel')
            ->whereKey($courseId)
            ->where('academic_year_id', $academicYearId)
            ->first();
        if (! $course) {
            throw new LibroDigitalException(
                'El curso no pertenece al año académico seleccionado.',
                'LCD_CURRICULUM_EXPORT_COURSE_SCOPE_INVALID',
                422,
            );
        }

        if ($book) {
            if ($book->status === BookStatus::Archived) {
                throw new LibroDigitalException(
                    'Los libros archivados no admiten nuevas exportaciones curriculares.',
                    'LCD_CURRICULUM_EXPORT_BOOK_ARCHIVED',
                    422,
                );
            }
            $belongsToSchool = (int) $book->school_id === (int) $school->id
                && (int) $book->academic_year_id === $academicYearId
                && (int) $book->course_section_id === (int) $course->id;
        } else {
            $belongsToSchool = Book::query()
                ->where('school_id', $school->id)
                ->where('academic_year_id', $academicYearId)
                ->where('course_section_id', $course->id)
                ->where('status', '<>', BookStatus::Archived->value)
                ->exists();
        }
        if (! $belongsToSchool) {
            throw new LibroDigitalException(
                'El curso no está respaldado por un libro vigente del establecimiento seleccionado.',
                'LCD_CURRICULUM_EXPORT_COURSE_SCOPE_INVALID',
                403,
            );
        }
        if ($book && (int) $book->course_section_id !== (int) $course->id) {
            throw new LibroDigitalException(
                'El curso solicitado no corresponde al libro seleccionado.',
                'LCD_CURRICULUM_EXPORT_COURSE_SCOPE_INVALID',
                422,
            );
        }

        $grade = $this->grades->fromEducationLevel($course->educationLevel);
        if ($grade === null) {
            throw new LibroDigitalException(
                'El nivel educativo del curso no se puede traducir a un grado curricular NT1–4M.',
                'LCD_CURRICULUM_EXPORT_COURSE_GRADE_UNRESOLVED',
                422,
            );
        }
        $level = $this->grades->levelForGrade($grade);
        $this->assertCompatible($filters, 'grade_code', $grade);
        $this->assertCompatible($filters, 'level_code', $level);

        return [
            ...$filters,
            'course_section_id' => (int) $course->id,
            'course_label' => (string) $course->display_name,
            'grade_code' => $grade,
            'level_code' => $level,
        ];
    }

    /** @param array<string, mixed> $filters */
    private function assertCompatible(array $filters, string $field, string $derived): void
    {
        if (isset($filters[$field]) && filled($filters[$field]) && mb_strtoupper(trim((string) $filters[$field])) !== $derived) {
            throw new LibroDigitalException(
                'El filtro '.$field.' no corresponde al curso seleccionado.',
                'LCD_CURRICULUM_EXPORT_COURSE_FILTER_CONFLICT',
                422,
                [['field' => 'filters.'.$field, 'derived_value' => $derived]],
            );
        }
    }
}
