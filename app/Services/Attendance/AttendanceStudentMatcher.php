<?php

namespace App\Services\Attendance;

use App\Models\CourseSection;
use App\Models\StudentEnrollment;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AttendanceStudentMatcher
{
    public function match(array $sourceStudents, CourseSection $course): array
    {
        $roster = StudentEnrollment::query()
            ->where('academic_year_id', $course->academic_year_id)
            ->where('course_section_id', $course->id)
            ->with('studentProfile:id,first_name,last_name,registered_name,rut')
            ->get()
            ->filter(fn (StudentEnrollment $enrollment) => $enrollment->studentProfile !== null)
            ->values();

        return array_map(fn (array $source) => $this->matchOne($source, $roster), $sourceStudents);
    }

    private function matchOne(array $source, Collection $roster): array
    {
        $ranked = $roster
            ->map(function (StudentEnrollment $enrollment) use ($source) {
                $student = $enrollment->studentProfile;
                $score = max(array_map(
                    fn (string $candidate) => $this->similarity((string) $source['name'], $candidate),
                    array_filter([
                        $student->registered_name_resolved,
                        $student->full_name,
                        trim($student->last_name.' '.$student->first_name),
                    ]),
                ));

                return [
                    'student_profile_id' => $student->id,
                    'student_enrollment_id' => $enrollment->id,
                    'name' => $student->registered_name_resolved,
                    'rut' => $student->rut,
                    'score' => round($score, 1),
                ];
            })
            ->sortByDesc('score')
            ->values();

        $best = $ranked->get(0);
        $second = $ranked->get(1);
        $isExact = ($best['score'] ?? 0) >= 99.9;
        $isAmbiguous = $best && $second && $best['score'] >= 78 && ($best['score'] - $second['score']) < 4;
        $isMatched = $best && $best['score'] >= 78 && ! $isAmbiguous;

        return [
            ...$source,
            'match_status' => $isExact ? 'exact' : ($isMatched ? 'fuzzy' : ($isAmbiguous ? 'ambiguous' : 'unmatched')),
            'matched_student_id' => $isMatched ? $best['student_profile_id'] : null,
            'matched_enrollment_id' => $isMatched ? $best['student_enrollment_id'] : null,
            'match_score' => $best['score'] ?? 0,
            'matched_student' => $isMatched ? $best : null,
            'candidates' => $ranked->take(5)->values()->all(),
        ];
    }

    private function similarity(string $source, string $candidate): float
    {
        $sourceNormal = $this->normalize($source);
        $candidateNormal = $this->normalize($candidate);
        $sourceSignature = $this->signature($sourceNormal);
        $candidateSignature = $this->signature($candidateNormal);

        if ($sourceNormal === $candidateNormal || $sourceSignature === $candidateSignature) {
            return 100.0;
        }

        similar_text($sourceSignature, $candidateSignature, $textScore);
        $sourceTokens = array_values(array_unique(explode(' ', $sourceNormal)));
        $candidateTokens = array_values(array_unique(explode(' ', $candidateNormal)));
        $intersection = count(array_intersect($sourceTokens, $candidateTokens));
        $union = count(array_unique([...$sourceTokens, ...$candidateTokens]));
        $tokenScore = $union > 0 ? ($intersection / $union) * 100 : 0;

        return ($textScore * 0.55) + ($tokenScore * 0.45);
    }

    private function normalize(string $value): string
    {
        $value = Str::lower(Str::ascii($value));
        $value = preg_replace('/^\s*\d+\s+/u', '', $value);
        $value = preg_replace('/[^a-z0-9]+/u', ' ', (string) $value);

        return trim((string) preg_replace('/\s+/u', ' ', (string) $value));
    }

    private function signature(string $value): string
    {
        $tokens = array_filter(explode(' ', $value));
        sort($tokens, SORT_STRING);

        return implode(' ', $tokens);
    }
}
