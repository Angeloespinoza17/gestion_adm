<?php

namespace App\Services\Schedule;

use App\Models\CourseSection;
use App\Models\EducationLevel;
use App\Models\Schedule\SchoolDayBlock;
use App\Models\Schedule\SchoolDayTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class JornadaService
{
    public function __construct(private readonly ScheduleTimeCalculator $calculator)
    {
    }

    public function create(array $payload): SchoolDayTemplate
    {
        return DB::transaction(function () use ($payload) {
            $blocks = $payload['blocks'] ?? [];
            unset($payload['blocks']);

            $jornada = SchoolDayTemplate::query()->create($payload);
            $this->syncBlocks($jornada, $blocks);

            return $jornada->fresh('blocks');
        });
    }

    public function update(SchoolDayTemplate $jornada, array $payload): SchoolDayTemplate
    {
        return DB::transaction(function () use ($jornada, $payload) {
            $blocks = $payload['blocks'] ?? null;
            unset($payload['blocks']);

            $jornada->update($payload);

            if (is_array($blocks)) {
                $this->syncBlocks($jornada, $blocks);
            }

            return $jornada->fresh('blocks');
        });
    }

    public function duplicate(SchoolDayTemplate $jornada, ?string $name = null): SchoolDayTemplate
    {
        return DB::transaction(function () use ($jornada, $name) {
            $copy = SchoolDayTemplate::query()->create([
                'academic_year_id' => $jornada->academic_year_id,
                'name' => $name ?: $jornada->name . ' copia',
                'start_time' => $jornada->start_time,
                'end_time' => $jornada->end_time,
                'days_of_week' => $jornada->days_of_week,
                'active' => false,
                'notes' => $jornada->notes,
            ]);

            foreach ($jornada->blocks as $block) {
                $copy->blocks()->create($block->only([
                    'day_of_week',
                    'start_time',
                    'end_time',
                    'type',
                    'label',
                    'order',
                    'assignable',
                    'pedagogical_hours_equivalent',
                ]));
            }

            return $copy->fresh('blocks');
        });
    }

    /**
     * @param array<int, int> $levelIds
     */
    public function assignToLevels(SchoolDayTemplate $jornada, array $levelIds): int
    {
        return EducationLevel::query()
            ->whereIn('id', $levelIds)
            ->update(['default_school_day_template_id' => $jornada->id]);
    }

    /**
     * @param array<int, int> $courseIds
     */
    public function assignToCourses(SchoolDayTemplate $jornada, array $courseIds): int
    {
        return CourseSection::query()
            ->whereIn('id', $courseIds)
            ->update(['school_day_template_id' => $jornada->id]);
    }

    public function jornadaForCourse(CourseSection $courseSection): ?SchoolDayTemplate
    {
        if ($courseSection->school_day_template_id) {
            return SchoolDayTemplate::query()->with('blocks')->find($courseSection->school_day_template_id);
        }

        $level = $courseSection->relationLoaded('educationLevel')
            ? $courseSection->educationLevel
            : $courseSection->educationLevel()->first();

        return $level?->default_school_day_template_id
            ? SchoolDayTemplate::query()->with('blocks')->find($level->default_school_day_template_id)
            : null;
    }

    /**
     * @param array<int, array<string, mixed>> $blocks
     */
    private function syncBlocks(SchoolDayTemplate $jornada, array $blocks): void
    {
        $this->validateBlocks($jornada, $blocks);

        $jornada->blocks()->delete();

        foreach ($blocks as $index => $block) {
            $jornada->blocks()->create([
                'day_of_week' => (int) $block['day_of_week'],
                'start_time' => $block['start_time'],
                'end_time' => $block['end_time'],
                'type' => $block['type'] ?? SchoolDayBlock::TYPE_PEDAGOGICAL,
                'label' => $block['label'] ?? null,
                'order' => $block['order'] ?? ($index + 1),
                'assignable' => $block['assignable'] ?? (($block['type'] ?? SchoolDayBlock::TYPE_PEDAGOGICAL) === SchoolDayBlock::TYPE_PEDAGOGICAL),
                'pedagogical_hours_equivalent' => $block['pedagogical_hours_equivalent'] ?? null,
            ]);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $blocks
     */
    private function validateBlocks(SchoolDayTemplate $jornada, array $blocks): void
    {
        $byDay = [];
        $jornadaStart = $jornada->start_time;
        $jornadaEnd = $jornada->end_time;

        foreach ($blocks as $index => $block) {
            $start = (string) ($block['start_time'] ?? '');
            $end = (string) ($block['end_time'] ?? '');
            $day = (int) ($block['day_of_week'] ?? 0);

            if (!$this->calculator->contains($jornadaStart, $jornadaEnd, $start, $end)) {
                throw ValidationException::withMessages([
                    'blocks' => "El bloque " . ($index + 1) . ' queda fuera de la jornada.',
                ]);
            }

            foreach ($byDay[$day] ?? [] as $existing) {
                if ($this->calculator->overlaps($start, $end, $existing['start_time'], $existing['end_time'])) {
                    throw ValidationException::withMessages([
                        'blocks' => 'La jornada tiene bloques solapados.',
                    ]);
                }
            }

            $byDay[$day][] = $block;
        }
    }
}
