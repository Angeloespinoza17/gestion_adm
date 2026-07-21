<?php

namespace Tests\Unit\Attendance;

use App\Services\Attendance\LirmiAttendancePdfParser;
use Tests\TestCase;

class LirmiAttendancePdfParserTest extends TestCase
{
    public function test_it_parses_the_anonymized_lirmi_monthly_matrix_exactly(): void
    {
        $fixture = json_decode(file_get_contents(
            base_path('tests/Fixtures/Attendance/lirmi_monthly_anonymized.json'),
        ), true, flags: JSON_THROW_ON_ERROR);
        $matrix = $this->matrix($fixture);
        $text = "CONTROL SUBVENCIONES\nLibro de clases: Asistencia Diaria - {$fixture['course']}\nAbril {$fixture['year']}";

        $result = app(LirmiAttendancePdfParser::class)->parsePage($text, $matrix);

        $this->assertSame($fixture['expected'], $result['summary']);
        $this->assertCount(24, $result['students']);
        $this->assertCount(21, $result['days']);
        $this->assertSame('2026-04-14', collect($result['days'])->firstWhere('is_anomaly', true)['date']);
        $this->assertSame('pending_confirmation', collect($result['days'])->firstWhere('is_anomaly', true)['confirmation_status']);
        $this->assertTrue(collect($result['validation'])->every('passed'));
    }

    public function test_it_parses_character_fragmented_headers_and_students_without_records(): void
    {
        $fixture = json_decode(file_get_contents(
            base_path('tests/Fixtures/Attendance/lirmi_monthly_anonymized.json'),
        ), true, flags: JSON_THROW_ON_ERROR);
        $matrix = $this->matrix($fixture, fragmentedHeader: true, includeStudentWithoutRecords: true);
        $text = "CONTROL SUBVENCIONES\nLibro de clases: Asistencia Diaria - {$fixture['course']}\nAbril {$fixture['year']}";

        $result = app(LirmiAttendancePdfParser::class)->parsePage($text, $matrix);

        $this->assertCount(25, $result['students']);
        $this->assertSame('Estudiante Anónima 25', $result['students'][24]['name']);
        $this->assertSame(0, $result['students'][24]['total']);
        $this->assertSame(30, max(array_keys(collect($result['students'][0]['records'])->keyBy('day')->all())));
        $this->assertSame(452, $result['summary']['present']);
        $this->assertSame(504, $result['summary']['possible']);
        $this->assertSame(4, $result['summary']['students_below_85']);
        $this->assertTrue(collect($result['validation'])->every('passed'));
    }

    public function test_it_accepts_a_current_month_with_only_elapsed_days_in_the_header(): void
    {
        $headerY = 1000.0;
        $rowY = 980.0;
        $matrix = [$this->entry(100, $headerY, 'Nº \\ Días')];
        $dayXs = [];
        $x = 260.0;
        for ($day = 1; $day <= 17; $day++) {
            $dayXs[$day] = $x;
            $matrix[] = $this->entry($x, $headerY, (string) $day);
            $x += 12.0;
        }
        $matrix[] = $this->entry(670, $headerY, 'A');
        $matrix[] = $this->entry(685, $headerY, 'I');
        $matrix[] = $this->entry(700, $headerY, 'T');
        $matrix[] = $this->entry(179, $rowY, '1');
        $matrix[] = $this->entry(190, $rowY, 'Estudiante Anónima');
        foreach ([13, 14, 15] as $day) {
            $matrix[] = $this->entry($dayXs[$day], $rowY + 0.5, '●');
        }
        $matrix[] = $this->entry(670, $rowY, '3');
        $matrix[] = $this->entry(685, $rowY, '0');
        $matrix[] = $this->entry(700, $rowY, '3');

        $result = app(LirmiAttendancePdfParser::class)->parsePage(
            "CONTROL SUBVENCIONES\nLibro de clases: Asistencia Diaria - 1º Básico A\nJulio 2026",
            $matrix,
        );

        $this->assertSame(3, $result['summary']['school_days']);
        $this->assertSame(3, $result['summary']['possible']);
        $this->assertSame([13, 14, 15], array_column($result['students'][0]['records'], 'day'));
    }

    private function matrix(
        array $fixture,
        bool $fragmentedHeader = false,
        bool $includeStudentWithoutRecords = false,
    ): array {
        $matrix = [];
        $headerY = 1000.0;
        $dayXs = [];
        $x = 260.0;
        if ($fragmentedHeader) {
            foreach (['N', 'º', '\\', 'D', 'í', 'a', 's'] as $index => $character) {
                $matrix[] = $this->entry(100 + ($index * 3), $headerY, $character);
            }
        } else {
            $matrix[] = $this->entry(100, $headerY, 'Nº \\ Días');
        }

        for ($day = 1; $day <= 30; $day++) {
            $dayXs[$day] = $x;
            if ($fragmentedHeader && $day >= 10) {
                foreach (str_split((string) $day) as $index => $digit) {
                    $matrix[] = $this->entry($x + ($index * 2.5), $headerY, $digit);
                }
            } else {
                $matrix[] = $this->entry($x, $headerY, (string) $day);
            }
            $x += in_array($day, [3, 4, 11, 18, 25], true) ? 10.5 : 12.5;
        }

        $matrix[] = $this->entry(670, $headerY, 'A');
        $matrix[] = $this->entry(685, $headerY, 'I');
        $matrix[] = $this->entry(700, $headerY, 'T');
        $availableAbsenceDays = array_values(array_diff($fixture['active_days'], [$fixture['anomaly_day']]));

        foreach ($fixture['absence_counts'] as $index => $absenceCount) {
            $row = $index + 1;
            $rowY = 980.0 - ($index * 12.0);
            $matrix[] = $this->entry(179, $rowY, (string) $row);
            $studentName = sprintf('Estudiante Anónima %02d', $row);
            if ($fragmentedHeader) {
                $matrix = [...$matrix, ...$this->fragmentedTextEntries(190, $rowY, $studentName)];
            } else {
                $matrix[] = $this->entry(190, $rowY, $studentName);
            }
            $extraAbsences = array_slice($availableAbsenceDays, $index % count($availableAbsenceDays), max(0, $absenceCount - 1));
            if (count($extraAbsences) < $absenceCount - 1) {
                $extraAbsences = [...$extraAbsences, ...array_slice($availableAbsenceDays, 0, ($absenceCount - 1) - count($extraAbsences))];
            }
            $absentDays = [$fixture['anomaly_day'], ...$extraAbsences];

            foreach ($fixture['active_days'] as $day) {
                $matrix[] = $this->entry($dayXs[$day], $rowY + 0.5, in_array($day, $absentDays, true) ? 'X' : '●');
            }

            $matrix[] = $this->entry(670, $rowY, (string) (21 - $absenceCount));
            $matrix[] = $this->entry(685, $rowY, (string) $absenceCount);
            $matrix[] = $this->entry(700, $rowY, '21');
        }

        if ($includeStudentWithoutRecords) {
            $rowY = 980.0 - (count($fixture['absence_counts']) * 12.0);
            $matrix[] = $this->entry(179, $rowY, '25');
            $matrix = [...$matrix, ...$this->fragmentedTextEntries(190, $rowY, 'Estudiante Anónima 25')];
            $matrix[] = $this->entry(670, $rowY, '0');
            $matrix[] = $this->entry(685, $rowY, '0');
            $matrix[] = $this->entry(700, $rowY, '0');
        }

        return $matrix;
    }

    private function fragmentedTextEntries(float $x, float $y, string $text): array
    {
        $entries = [];
        foreach (mb_str_split($text) as $character) {
            if ($character === ' ') {
                $x += 1.5;

                continue;
            }

            $entries[] = $this->entry($x, $y, $character);
            $x += 2.0;
        }

        return $entries;
    }

    private function entry(float $x, float $y, string $text): array
    {
        return [[1, 0, 0, 1, $x, $y], $text];
    }
}
