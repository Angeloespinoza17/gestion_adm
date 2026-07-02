<?php

namespace Tests\Unit\Students;

use App\Http\Controllers\Students\EducationLevelController;
use App\Models\AcademicYear;
use App\Models\CourseSection;
use App\Models\EducationLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class EducationLevelControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_blocks_deleting_a_level_with_associated_courses(): void
    {
        $level = EducationLevel::query()->create([
            'name' => '7° básico',
            'order' => 9,
            'type' => 'basica',
        ]);

        $year = AcademicYear::query()->create([
            'name' => '2026',
            'year' => 2026,
            'is_active' => true,
        ]);

        CourseSection::query()->create([
            'academic_year_id' => $year->id,
            'education_level_id' => $level->id,
            'section_name' => 'A',
            'display_name' => '7° básico A',
            'capacity' => 35,
            'active' => true,
        ]);

        $response = app(EducationLevelController::class)->destroy($level);

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $this->assertDatabaseHas('education_levels', ['id' => $level->id]);
    }

    public function test_it_deletes_a_level_without_associated_courses(): void
    {
        $level = EducationLevel::query()->create([
            'name' => 'Nivel temporal',
            'order' => 90,
            'type' => 'basica',
        ]);

        $response = app(EducationLevelController::class)->destroy($level);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertDatabaseMissing('education_levels', ['id' => $level->id]);
    }
}
