<?php

namespace Tests\Feature\LibroDigital;

use App\Models\AcademicYear;
use App\Models\CourseSection;
use App\Models\EducationLevel;
use App\Models\LibroDigital\Book;
use App\Models\LibroDigital\CurriculumCatalog;
use App\Models\LibroDigital\CurriculumCatalogActivation;
use App\Models\LibroDigital\CurriculumImportBatch;
use App\Models\LibroDigital\CurriculumSource;
use App\Models\LibroDigital\LearningObjective;
use App\Models\LibroDigital\LearningObjectiveSource;
use App\Models\LibroDigital\NormativeSource;
use App\Models\LibroDigital\RegulatoryProfile;
use App\Models\LibroDigital\School;
use App\Models\LibroDigital\SubjectCurriculumLink;
use App\Models\LibroDigital\TeachingGroup;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Schedule\ScheduleSubject;
use App\Models\User;
use App\Services\LibroDigital\CurriculumObjectiveIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CurriculumVisualizationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_visualization_requires_authentication_and_the_same_curriculum_permission_as_the_table(): void
    {
        [, $school, $year] = $this->context();
        $url = $this->url($school, $year);

        $this->getJson($url)->assertUnauthorized();

        $user = User::factory()->create(['active' => true]);
        $role = Role::query()->create(['name' => 'Solo acceso LCD', 'slug' => 'solo-acceso-lcd', 'active' => true]);
        $permission = Permission::query()->firstOrCreate(
            ['slug' => 'libro_digital.access'],
            ['name' => 'Acceso al libro digital', 'active' => true],
        );
        $role->permissions()->sync([$permission->id]);
        $user->roles()->sync([$role->id]);
        $school->users()->attach($user->id, ['active' => true]);

        $this->actingAs($user)->getJson($url)->assertForbidden();
    }

    public function test_empty_catalog_returns_a_normalized_empty_visualization(): void
    {
        [$user, $school, $year] = $this->context();

        $this->actingAs($user)->getJson($this->url($school, $year))
            ->assertOk()
            ->assertJsonPath('meta.total_objectives', 0)
            ->assertJsonPath('meta.filtered_total_objectives', 0)
            ->assertJsonPath('meta.leaves_included', false)
            ->assertJsonPath('meta.catalog_status', 'not_imported')
            ->assertJsonPath('root.objective_count', 0)
            ->assertJsonPath('root.children', [])
            ->assertJsonCount(1, 'graph.nodes')
            ->assertJsonPath('graph.links', []);
    }

    public function test_visualization_total_matches_the_table_and_reuses_all_filter_semantics(): void
    {
        [$user, $school, $year] = $this->context();
        $fixture = $this->smallCatalog($school, $year);
        $base = [
            'school_id' => $school->id,
            'academic_year_id' => $year->id,
            'status' => 'all',
        ];

        $table = $this->actingAs($user)->getJson('/api/libro-digital/v1/curriculum/objectives?'.http_build_query($base))
            ->assertOk();
        $visualization = $this->getJson('/api/libro-digital/v1/curriculum/objectives/visualization?'.http_build_query([
            ...$base,
            'hierarchy' => 'subject,curricular_group,objective',
            'include_leaves' => 1,
        ]))->assertOk();

        $this->assertSame($table->json('meta.total'), $visualization->json('meta.total_objectives'));
        $this->assertSame($table->json('meta.summary.filtered_objectives'), $visualization->json('meta.total_objectives'));
        $visualization->assertJsonPath('meta.total_objectives', 4)
            ->assertJsonPath('meta.available_objectives', 3)
            ->assertJsonPath('meta.unavailable_objectives', 1)
            ->assertJsonPath('root.objective_count', 4);

        foreach ([
            ['filter' => ['subject_code' => 'LEN'], 'expected' => 2],
            ['filter' => ['grade_code' => '6B'], 'expected' => 1],
            ['filter' => ['axis_code' => 'LECTURA'], 'expected' => 1],
            ['filter' => ['status' => 'inactive'], 'expected' => 1],
            ['filter' => ['query' => 'fracciones'], 'expected' => 1],
            ['filter' => ['source' => $fixture['language_source']->source_key], 'expected' => 2],
        ] as $case) {
            $params = [...$base, ...$case['filter'], 'hierarchy' => 'subject,objective'];
            $this->getJson('/api/libro-digital/v1/curriculum/objectives/visualization?'.http_build_query($params))
                ->assertOk()
                ->assertJsonPath('meta.total_objectives', $case['expected'])
                ->assertJsonPath('root.objective_count', $case['expected']);
        }
    }

    public function test_dimensions_are_allowlisted_and_objective_must_be_the_last_level(): void
    {
        [$user, $school, $year] = $this->context();

        $this->actingAs($user)->getJson($this->url($school, $year, [
            'hierarchy' => 'subject,drop_table,objective',
        ]))->assertUnprocessable()->assertJsonValidationErrors('hierarchy.1');

        $this->getJson($this->url($school, $year, [
            'hierarchy' => 'subject,objective,grade',
        ]))->assertUnprocessable()->assertJsonValidationErrors('hierarchy');

        $this->getJson($this->url($school, $year, [
            'max_depth' => 6,
        ]))->assertUnprocessable()->assertJsonValidationErrors('max_depth');

        $this->getJson($this->url($school, $year, [
            'view' => 'sankey',
            'hierarchy' => 'education_level,formation,subject,curricular_group,objective',
        ]))->assertUnprocessable()->assertJsonValidationErrors('hierarchy');

        $this->getJson($this->url($school, $year, [
            'view' => 'sankey',
            'hierarchy' => 'education_level,formation,subject,curricular_group,objective',
            'max_depth' => 4,
        ]))->assertOk()
            ->assertJsonPath('meta.max_depth', 4)
            ->assertJsonCount(4, 'meta.hierarchy');

        $this->getJson($this->url($school, $year, [
            'view' => 'radial_tree',
            'max_depth' => 4,
        ]))->assertUnprocessable()->assertJsonValidationErrors('max_depth');

        $this->getJson($this->url($school, $year, [
            'view' => 'radial_tree',
            'hierarchy' => 'education_level,subject,curricular_group,objective',
        ]))->assertOk()
            ->assertJsonPath('meta.max_depth', 3)
            ->assertJsonCount(3, 'meta.hierarchy');

        $this->getJson($this->url($school, $year, [
            'view' => 'mind_map',
        ]))->assertOk()->assertJsonPath('graph.node_limit', 250);
    }

    public function test_multiple_source_relationships_and_corrupt_canonical_duplicates_never_duplicate_objectives(): void
    {
        [$user, $school, $year] = $this->context();
        $this->smallCatalog($school, $year);

        $response = $this->actingAs($user)->getJson($this->url($school, $year, [
            'status' => 'all',
            'hierarchy' => 'source,objective',
            'include_leaves' => 1,
        ]))->assertOk()
            ->assertJsonPath('meta.total_objectives', 4)
            ->assertJsonPath('meta.canonical_source_conflicts', 1)
            ->assertJsonPath('root.objective_count', 4);

        $objectiveIds = collect($this->nodesOfType($response->json('root'), 'objective'))->pluck('id');
        $this->assertCount(4, $objectiveIds);
        $this->assertCount(4, $objectiveIds->unique());
    }

    public function test_radial_tree_limits_the_initial_depth_but_allows_relative_root_drilldown(): void
    {
        [$user, $school, $year] = $this->context();
        $this->smallCatalog($school, $year);
        $params = [
            'status' => 'all',
            'view' => 'radial_tree',
            'hierarchy' => 'education_level,formation,subject,curricular_group,objective',
            'include_leaves' => 1,
        ];

        $initial = $this->actingAs($user)->getJson($this->url($school, $year, $params))
            ->assertOk()
            ->assertJsonPath('meta.max_depth', 3)
            ->assertJsonCount(3, 'meta.hierarchy')
            ->assertJsonPath('meta.leaves_included', false)
            ->assertJsonPath('meta.aggregated', true);
        $this->assertCount(0, $this->nodesOfType($initial->json('root'), 'objective'));

        $subject = $this->nodesOfType($initial->json('root'), 'subject')[0];
        $this->assertTrue($subject['has_children']);
        $this->assertSame([], $subject['children']);
        $this->assertTrue(data_get($subject, 'metadata.children_truncated'));

        $drilldown = $this->getJson($this->url($school, $year, [
            ...$params,
            'root_node' => $subject['id'],
            'max_depth' => 4,
        ]))->assertOk()
            ->assertJsonPath('meta.max_depth', 4)
            ->assertJsonCount(4, 'meta.hierarchy')
            ->assertJsonPath('meta.leaves_included', true)
            ->assertJsonPath('meta.aggregated', false)
            ->assertJsonPath('root.id', $subject['id'])
            ->assertJsonPath('root.depth', 0);

        $this->assertCount(
            (int) $drilldown->json('meta.total_objectives'),
            $this->nodesOfType($drilldown->json('root'), 'objective'),
        );
    }

    public function test_large_results_are_aggregated_and_a_safe_root_node_enables_bounded_drilldown(): void
    {
        config()->set('libro_digital.curriculum_visualizations.cache_ttl_seconds', 0);
        [$user, $school, $year] = $this->context();
        $this->largeCatalog($school, $year, 501);
        $params = [
            'school_id' => $school->id,
            'academic_year_id' => $year->id,
            'status' => 'all',
            'hierarchy' => 'subject,objective',
            'include_leaves' => 1,
        ];

        $initial = $this->actingAs($user)->getJson('/api/libro-digital/v1/curriculum/objectives/visualization?'.http_build_query($params))
            ->assertOk()
            ->assertJsonPath('meta.total_objectives', 501)
            ->assertJsonPath('meta.leaves_included', false)
            ->assertJsonPath('meta.aggregated', true)
            ->assertJsonPath('meta.leaf_threshold', 500);
        $this->assertCount(0, $this->nodesOfType($initial->json('root'), 'objective'));
        $this->assertCount(2, $initial->json('root.children'));

        $subjectNode = $initial->json('root.children.0');
        $drilldown = $this->getJson('/api/libro-digital/v1/curriculum/objectives/visualization?'.http_build_query([
            ...$params,
            'root_node' => $subjectNode['id'],
        ]))->assertOk()
            ->assertJsonPath('meta.total_objectives', 400)
            ->assertJsonPath('meta.filtered_total_objectives', 501)
            ->assertJsonPath('meta.leaves_included', true)
            ->assertJsonPath('meta.aggregated', false)
            ->assertJsonPath('root.id', $subjectNode['id'])
            ->assertJsonPath('root.parent_id', null)
            ->assertJsonPath('root.depth', 0)
            ->assertJsonPath('graph.node_limit', 300)
            ->assertJsonPath('graph.truncated', true);
        $this->assertCount(400, $this->nodesOfType($drilldown->json('root'), 'objective'));

        $this->getJson('/api/libro-digital/v1/curriculum/objectives/visualization?'.http_build_query([
            ...$params,
            'root_node' => 'subject:'.str_repeat('f', 64),
        ]))->assertUnprocessable()->assertJsonValidationErrors('root_node');
    }

    public function test_catalog_scope_ignores_search_filters_but_remains_inside_the_activated_context(): void
    {
        [$user, $school, $year] = $this->context();
        $this->smallCatalog($school, $year);

        $this->actingAs($user)->getJson($this->url($school, $year, [
            'scope' => 'filtered',
            'subject_code' => 'LEN',
        ]))->assertOk()->assertJsonPath('meta.total_objectives', 2);

        $this->getJson($this->url($school, $year, [
            'scope' => 'catalog',
            'subject_code' => 'LEN',
        ]))->assertOk()
            ->assertJsonPath('meta.scope', 'catalog')
            ->assertJsonPath('meta.total_objectives', 4);
    }

    public function test_book_visualization_uses_the_same_active_subject_and_transversal_scope_as_the_book_table(): void
    {
        [$user, $school, $year] = $this->context();
        $fixture = $this->smallCatalog($school, $year);
        $profile = RegulatoryProfile::query()->where('code', 'CL-CURRICULUM')->firstOrFail();
        $level = EducationLevel::query()->create([
            'name' => '5° Básico', 'order' => ((int) EducationLevel::query()->max('order')) + 1, 'type' => 'basica',
        ]);
        $course = CourseSection::query()->create([
            'academic_year_id' => $year->id,
            'education_level_id' => $level->id,
            'section_name' => 'A',
            'display_name' => '5° Básico A',
            'capacity' => 40,
            'active' => true,
        ]);
        $book = Book::query()->create([
            'school_id' => $school->id,
            'academic_year_id' => $year->id,
            'regulatory_profile_id' => $profile->id,
            'course_section_id' => $course->id,
            'code' => 'BOOK-5B-A',
            'rbd_snapshot' => $school->rbd,
            'year_snapshot' => $year->year,
            'level_code' => 'BASICA',
            'grade_code' => '5B',
            'course_label' => $course->display_name,
            'status' => 'draft',
            'source_format' => 'native',
        ]);
        $language = ScheduleSubject::query()->where('code', 'LEN')->firstOrFail();
        TeachingGroup::query()->create([
            'school_id' => $school->id,
            'academic_year_id' => $year->id,
            'book_id' => $book->id,
            'course_section_id' => $course->id,
            'schedule_subject_id' => $language->id,
            'code' => '5B-LEN-A',
            'name' => '5° Básico A · Lenguaje',
            'course_snapshot' => $course->display_name,
            'subject_snapshot' => $language->name,
            'valid_from' => $year->starts_at,
            'valid_to' => $year->ends_at,
            'status' => 'active',
        ]);
        SubjectCurriculumLink::query()->create([
            'school_id' => $school->id,
            'academic_year_id' => $year->id,
            'schedule_subject_id' => $language->id,
            'curriculum_catalog_id' => $fixture['catalog']->id,
            'level_code' => 'BASICA',
            'grade_code' => '5B',
            'curriculum_track' => 'GENERAL',
            'scope_key' => 'BASICA:5B:GENERAL',
            'valid_from' => $year->starts_at,
            'valid_to' => $year->ends_at,
            'active' => true,
        ]);
        $params = [
            'school_id' => $school->id,
            'academic_year_id' => $year->id,
            'book_id' => $book->public_id,
            'schedule_subject_id' => $language->id,
            'status' => 'active',
        ];

        $table = $this->actingAs($user)->getJson('/api/libro-digital/v1/curriculum/objectives?'.http_build_query($params))
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
        $visualization = $this->getJson('/api/libro-digital/v1/curriculum/objectives/visualization?'.http_build_query([
            ...$params,
            'hierarchy' => 'subject,objective',
        ]))->assertOk()
            ->assertJsonPath('meta.total_objectives', 2)
            ->assertJsonPath('meta.available_objectives', 2);

        $this->assertSame($table->json('meta.total'), $visualization->json('meta.total_objectives'));
    }

    public function test_parvularia_subject_is_exposed_as_a_nucleus_without_repeating_the_same_group(): void
    {
        [$user, $school, $year] = $this->context();
        [$catalog] = $this->activatedCatalog($school, $year, 1);
        $nucleus = ScheduleSubject::query()->create([
            'code' => 'CES',
            'name' => 'Comprensión del entorno sociocultural',
            'area' => 'Currículum Nacional · PARVULARIA',
            'color' => '#0F766E',
            'active' => true,
        ]);
        LearningObjective::query()->create([
            'public_id' => (string) Str::ulid(),
            'curriculum_catalog_id' => $catalog->id,
            'schedule_subject_id' => $nucleus->id,
            'level_code' => 'PARVULARIA',
            'grade_code' => 'NT1',
            'curriculum_track' => 'PARVULARIA',
            'axis_code' => 'COMPRENSION_DEL_ENTORNO_SOCIOCULTURAL',
            'objective_type' => 'OA',
            'code' => 'OA 01 CES NT',
            'objective_key' => hash('sha256', 'parvularia-visual-objective'),
            'description' => 'Comprender el entorno sociocultural.',
            'active' => true,
        ]);

        $response = $this->actingAs($user)->getJson($this->url($school, $year, [
            'status' => 'all',
            'hierarchy' => 'formation,subject,grade,curricular_group,objective',
            'include_leaves' => 1,
        ]))->assertOk()
            ->assertJsonPath('meta.total_objectives', 1)
            ->assertJsonPath('meta.total_curricular_groups', 1);

        $subjectNodes = $this->nodesOfType($response->json('root'), 'subject');
        $this->assertCount(1, $subjectNodes);
        $this->assertSame('Núcleo', data_get($subjectNodes[0], 'metadata.classification_label'));
        $this->assertSame('nucleus', data_get($subjectNodes[0], 'metadata.semantic_type'));
        $this->assertCount(0, $this->nodesOfType($response->json('root'), 'curricular_group'));
        $this->assertCount(1, $this->nodesOfType($response->json('root'), 'objective'));
    }

    public function test_missing_and_sin_codigo_groups_are_one_unclassified_bucket(): void
    {
        [$user, $school, $year] = $this->context();
        [$catalog] = $this->activatedCatalog($school, $year, 2);
        $subject = ScheduleSubject::query()->create([
            'code' => 'CNA', 'name' => 'Ciencias Naturales', 'area' => 'General', 'color' => '#2563EB', 'active' => true,
        ]);
        $this->objective($catalog, $subject, 'OA-SIN-01', 'Objetivo sin código de eje.', '5B', 'SIN_CODIGO', true);
        $this->objective($catalog, $subject, 'OA-SIN-02', 'Objetivo sin clasificación.', '5B', null, true);

        $params = [
            'status' => 'all',
            'hierarchy' => 'curricular_group,objective',
            'include_leaves' => 1,
        ];
        $response = $this->actingAs($user)->getJson($this->url($school, $year, $params))->assertOk()
            ->assertJsonPath('meta.total_objectives', 2)
            ->assertJsonPath('meta.total_curricular_groups', 1)
            ->assertJsonCount(1, 'root.children')
            ->assertJsonPath('root.children.0.name', 'Sin clasificación principal')
            ->assertJsonPath('root.children.0.objective_count', 2);

        $this->assertCount(2, $this->nodesOfType($response->json('root'), 'objective'));
        $bucketId = $response->json('root.children.0.id');
        $this->getJson($this->url($school, $year, [
            ...$params,
            'root_node' => $bucketId,
        ]))->assertOk()
            ->assertJsonPath('meta.total_objectives', 2)
            ->assertJsonPath('root.id', $bucketId)
            ->assertJsonPath('root.objective_count', 2);
    }

    /** @return array{User,School,AcademicYear} */
    private function context(): array
    {
        $user = User::factory()->create(['active' => true]);
        $role = Role::query()->firstOrCreate(
            ['slug' => 'super_admin'],
            ['name' => 'Super Admin', 'active' => true],
        );
        $user->roles()->syncWithoutDetaching([$role->id]);
        $school = School::query()->create([
            'rbd' => '12345-6',
            'name' => 'Escuela visual',
            'timezone' => 'America/Santiago',
            'active' => true,
        ]);
        $year = AcademicYear::factory()->create([
            'year' => 2035,
            'name' => '2035',
            'starts_at' => '2035-03-01',
            'ends_at' => '2035-12-20',
            'is_active' => true,
            'is_closed' => false,
        ]);
        $profile = RegulatoryProfile::query()->create([
            'code' => 'CL-CURRICULUM',
            'name' => 'Perfil curricular',
            'version' => '1',
            'effective_from' => '2030-01-01',
            'retention_years' => 6,
            'rules_snapshot' => [],
            'active' => true,
        ]);
        $school->academicYears()->attach($year->id, [
            'regulatory_profile_id' => $profile->id,
            'rbd_snapshot' => $school->rbd,
            'year_snapshot' => $year->year,
            'timezone_snapshot' => $school->timezone,
            'active' => true,
        ]);

        return [$user, $school, $year];
    }

    /** @return array{catalog:CurriculumCatalog,language_source:CurriculumSource} */
    private function smallCatalog(School $school, AcademicYear $year): array
    {
        [$catalog, $batch] = $this->activatedCatalog($school, $year, 4);
        $language = ScheduleSubject::query()->create([
            'code' => 'LEN', 'name' => 'Lenguaje', 'area' => 'General', 'color' => '#2563EB', 'active' => true,
        ]);
        $math = ScheduleSubject::query()->create([
            'code' => 'MAT', 'name' => 'Matemática', 'area' => 'General', 'color' => '#0F766E', 'active' => true,
        ]);
        $languageSource = $this->source($catalog, 'SRC-LEN', 'Bases de Lenguaje');
        $mathSource = $this->source($catalog, 'SRC-MAT', 'Bases de Matemática');
        $legalSource = $this->source($catalog, 'SRC-LEGAL', 'Decreto curricular');
        $alternateSource = $this->source($catalog, 'SRC-ALT', 'Fuente canónica duplicada');

        $objectives = [
            $this->objective($catalog, $language, 'OA-LEN-01', 'Comprender textos narrativos.', '5B', 'LECTURA', true),
            $this->objective($catalog, $language, 'OA-LEN-02', 'Escribir textos breves.', '5B', 'ESCRITURA', false),
            $this->objective($catalog, $math, 'OA-MAT-01', 'Resolver problemas con fracciones.', '6B', 'NUMEROS', true),
            $this->objective($catalog, null, 'OAT-01', 'Participar responsablemente.', '5B', 'OAT_CICLO', true, 'OAT'),
        ];
        foreach ($objectives as $index => $objective) {
            $canonical = $index < 2 ? $languageSource : $mathSource;
            $this->relationship($objective, $canonical, 'canonical_text');
            $this->relationship($objective, $legalSource, 'legal_basis');
        }
        // Simula corrupción histórica: el servicio debe advertirla y elegir una
        // fuente determinista sin duplicar el OA ni inventar una relación.
        $this->relationship($objectives[0], $alternateSource, 'canonical_text');
        $batch->forceFill(['objective_count' => 4])->save();

        return ['catalog' => $catalog, 'language_source' => $languageSource];
    }

    private function largeCatalog(School $school, AcademicYear $year, int $count): void
    {
        [$catalog] = $this->activatedCatalog($school, $year, $count);
        $subjectA = ScheduleSubject::query()->create([
            'code' => 'AAA', 'name' => 'Asignatura A', 'area' => 'General', 'color' => '#2563EB', 'active' => true,
        ]);
        $subjectB = ScheduleSubject::query()->create([
            'code' => 'BBB', 'name' => 'Asignatura B', 'area' => 'General', 'color' => '#0F766E', 'active' => true,
        ]);
        $now = now();
        $rows = [];
        for ($index = 1; $index <= $count; $index++) {
            $rows[] = [
                'public_id' => (string) Str::ulid(),
                'curriculum_catalog_id' => $catalog->id,
                'schedule_subject_id' => $index <= 400 ? $subjectA->id : $subjectB->id,
                'level_code' => 'BASICA',
                'grade_code' => '5B',
                'curriculum_track' => 'GENERAL',
                'axis_code' => 'LECTURA',
                'unit_code' => null,
                'objective_type' => 'OA',
                'code' => 'OA-BULK-'.str_pad((string) $index, 4, '0', STR_PAD_LEFT),
                'objective_key' => hash('sha256', 'bulk-objective-'.$index),
                'description' => 'Objetivo curricular de carga '.$index.'.',
                'indicators' => null,
                'active' => true,
                'source_page' => null,
                'source_row_hash' => hash('sha256', 'bulk-row-'.$index),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        foreach (array_chunk($rows, 25) as $chunk) {
            DB::table('lcd_learning_objectives')->insert($chunk);
        }
    }

    /** @return array{CurriculumCatalog,CurriculumImportBatch} */
    private function activatedCatalog(School $school, AcademicYear $year, int $count): array
    {
        $hash = hash('sha256', 'visual-catalog-'.$school->id.'-'.$year->id);
        $catalog = CurriculumCatalog::query()->create([
            'code' => 'CAT-VISUAL',
            'name' => 'Catálogo visual',
            'version' => '1',
            'authority' => 'MINEDUC',
            'source_url' => 'https://www.curriculumnacional.cl/',
            'source_hash' => $hash,
            'active' => true,
        ]);
        $batch = CurriculumImportBatch::query()->create([
            'school_id' => $school->id,
            'academic_year_id' => $year->id,
            'curriculum_catalog_id' => $catalog->id,
            'idempotency_key' => hash('sha256', 'visual-batch-'.$school->id.'-'.$year->id),
            'status' => CurriculumImportBatch::STATUS_ACTIVATED,
            'catalog_code' => $catalog->code,
            'catalog_version' => $catalog->version,
            'original_name' => 'visual.xlsx',
            'private_path' => 'private/visual.xlsx.enc',
            'source_hash' => hash('sha256', 'visual-workbook-'.$school->id.'-'.$year->id),
            'objective_count' => $count,
            'requested_at' => now('UTC'),
            'completed_at' => now('UTC'),
        ]);
        CurriculumCatalogActivation::query()->create([
            'school_id' => $school->id,
            'academic_year_id' => $year->id,
            'curriculum_catalog_id' => $catalog->id,
            'import_batch_id' => $batch->id,
            'activation_version' => 1,
            'idempotency_key' => hash('sha256', 'visual-activation-'.$school->id.'-'.$year->id),
            'status' => CurriculumCatalogActivation::STATUS_ACTIVATED,
            'scope_snapshot' => ['complete_nt1_4m' => true],
            'decision_hash' => hash('sha256', 'visual-decision-'.$school->id.'-'.$year->id),
            'requested_at' => now('UTC'),
            'activated_at' => now('UTC'),
        ]);

        return [$catalog, $batch];
    }

    private function source(CurriculumCatalog $catalog, string $key, string $name): CurriculumSource
    {
        $hash = hash('sha256', $key);
        $normative = NormativeSource::query()->create([
            'title' => $name,
            'authority' => 'MINEDUC',
            'document_number' => $key,
            'source_url' => 'https://www.curriculumnacional.cl/'.$key.'.pdf',
            'sha256' => $hash,
            'private_path' => 'private/'.$key.'.pdf.enc',
            'status' => 'verified',
        ]);

        return CurriculumSource::query()->create([
            'curriculum_catalog_id' => $catalog->id,
            'normative_source_id' => $normative->id,
            'source_key' => $key,
            'source_scope' => 'GENERAL_1B_6B',
            'source_name' => $name,
            'authority' => 'MINEDUC',
            'document_number' => $key,
            'source_url' => 'https://www.curriculumnacional.cl/'.$key.'.pdf',
            'declared_sha256' => $hash,
            'verified_sha256' => $hash,
            'status' => 'verified',
        ]);
    }

    private function objective(
        CurriculumCatalog $catalog,
        ?ScheduleSubject $subject,
        string $code,
        string $description,
        string $grade,
        ?string $axis,
        bool $active,
        string $type = 'OA',
    ): LearningObjective {
        return LearningObjective::query()->create([
            'public_id' => (string) Str::ulid(),
            'curriculum_catalog_id' => $catalog->id,
            'schedule_subject_id' => $subject?->id,
            'level_code' => 'BASICA',
            'grade_code' => $grade,
            'curriculum_track' => 'GENERAL',
            'axis_code' => $axis,
            'objective_type' => $type,
            'code' => $code,
            'objective_key' => CurriculumObjectiveIdentity::key([
                'code' => $code,
                'objective_type' => $type,
                'subject_code' => $subject?->code,
                'level_code' => 'BASICA',
                'grade_code' => $grade,
                'curriculum_track' => 'GENERAL',
                'axis_code' => $axis,
            ]),
            'description' => $description,
            'active' => $active,
        ]);
    }

    private function relationship(LearningObjective $objective, CurriculumSource $source, string $role): void
    {
        LearningObjectiveSource::query()->create([
            'learning_objective_id' => $objective->id,
            'curriculum_source_id' => $source->id,
            'source_role' => $role,
            'source_locator' => 'p. '.$objective->id,
            'relationship_hash' => hash('sha256', $objective->id.'|'.$source->id.'|'.$role),
            'source_snapshot' => ['source_key' => $source->source_key],
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function nodesOfType(array $node, string $type): array
    {
        $nodes = ($node['type'] ?? null) === $type ? [$node] : [];
        foreach ($node['children'] ?? [] as $child) {
            $nodes = [...$nodes, ...$this->nodesOfType($child, $type)];
        }

        return $nodes;
    }

    /** @param array<string, mixed> $extra */
    private function url(School $school, AcademicYear $year, array $extra = []): string
    {
        return '/api/libro-digital/v1/curriculum/objectives/visualization?'.http_build_query([
            'school_id' => $school->id,
            'academic_year_id' => $year->id,
            ...$extra,
        ]);
    }
}
