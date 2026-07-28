<?php

namespace Tests\Feature\Library;

use App\Models\AcademicYear;
use App\Models\CourseSection;
use App\Models\EducationLevel;
use App\Models\Library\BibliotecaCategoria;
use App\Models\Library\BibliotecaEjemplar;
use App\Models\Library\BibliotecaEspacio;
use App\Models\Library\BibliotecaObra;
use App\Models\Library\BibliotecaPase;
use App\Models\Library\BibliotecaPrestamo;
use App\Models\Library\BibliotecaSubcategoria;
use App\Models\Library\BibliotecaTextoEntrega;
use App\Models\Library\BibliotecaTextoOrden;
use App\Models\Library\BibliotecaTextoTitulo;
use App\Models\Role;
use App\Models\Staff;
use App\Models\StudentEnrollment;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\Library\OpenLibraryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BibliotecaExpandedModuleTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-07-27 10:00:00');

        $this->user = User::factory()->create(['active' => true]);
        $role = Role::query()->create([
            'name' => 'Super administrador',
            'slug' => 'super_admin',
            'active' => true,
        ]);
        $this->user->roles()->attach($role);

        Sanctum::actingAs($this->user);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_it_creates_a_book_with_avis_codes_and_multiple_copies(): void
    {
        $locationId = (int) \DB::table('biblioteca_ubicaciones')->where('code', 'SALA-1')->value('id');

        $response = $this->postJson('/api/biblioteca/obras', [
            'material_type' => 'libro',
            'title' => 'El principito',
            'main_author' => 'Antoine de Saint-Exupéry',
            'publisher' => 'Editorial de prueba',
            'isbn' => '9780156013987',
            'biblioteca_ubicacion_id' => $locationId,
            'general_status' => 'disponible',
            'quantity' => 3,
            'open_library_work_key' => '/works/OL45804W',
            'source_metadata' => ['provider' => 'open_library'],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.title', 'El principito')
            ->assertJsonCount(3, 'data.ejemplares');

        $obra = BibliotecaObra::query()->firstOrFail();
        $this->assertMatchesRegularExpression('/^BIB-OBR-2026-\d{4}$/', $obra->internal_code);
        $this->assertSame(3, $obra->total_copies);
        $this->assertSame(3, $obra->available_copies);
        $this->assertSame(3, BibliotecaEjemplar::query()->count());
        $this->assertSame(3, BibliotecaEjemplar::query()->where('biblioteca_ubicacion_id', $locationId)->count());
        $this->assertSame(
            3,
            BibliotecaEjemplar::query()
                ->get()
                ->filter(fn (BibliotecaEjemplar $copy) => preg_match('/^BIB-EJ-2026-\d{4}$/', $copy->code))
                ->count()
        );
    }

    public function test_it_manages_subcategories_and_assigns_them_to_a_title(): void
    {
        $category = BibliotecaCategoria::query()->create([
            'name' => 'Narrativa',
            'slug' => 'narrativa',
            'code' => 'NAR',
            'color' => '#556ee6',
            'sort_order' => 1,
            'active' => true,
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
        ]);

        $subcategoryResponse = $this->postJson('/api/biblioteca/subcategorias', [
            'biblioteca_categoria_id' => $category->id,
            'name' => 'Novela histórica',
            'description' => 'Narraciones ambientadas en periodos históricos.',
            'sort_order' => 1,
            'active' => true,
        ]);

        $subcategoryResponse
            ->assertCreated()
            ->assertJsonPath('data.name', 'Novela histórica')
            ->assertJsonPath('data.biblioteca_categoria_id', $category->id);

        $subcategory = BibliotecaSubcategoria::query()->firstOrFail();

        $this->getJson('/api/biblioteca/categorias')
            ->assertOk()
            ->assertJsonPath('data.0.subcategorias.0.name', 'Novela histórica');

        $this->getJson('/api/biblioteca/catalogs')
            ->assertOk()
            ->assertJsonPath('subcategories.0.id', $subcategory->id)
            ->assertJsonPath(
                'subcategories.0.biblioteca_categoria_id',
                $category->id
            );

        $this->postJson('/api/biblioteca/obras', [
            'material_type' => 'libro',
            'title' => 'La ciudad y los perros',
            'main_author' => 'Mario Vargas Llosa',
            'biblioteca_categoria_id' => $category->id,
            'biblioteca_subcategoria_id' => $subcategory->id,
            'general_status' => 'disponible',
            'quantity' => 1,
        ])
            ->assertCreated()
            ->assertJsonPath('data.subcategoria.id', $subcategory->id);

        $this->assertDatabaseHas('biblioteca_obras', [
            'title' => 'La ciudad y los perros',
            'biblioteca_categoria_id' => $category->id,
            'biblioteca_subcategoria_id' => $subcategory->id,
            'subcategory' => 'Novela histórica',
        ]);

        $obra = BibliotecaObra::query()->where('title', 'La ciudad y los perros')->firstOrFail();
        $copy = $obra->ejemplares()->firstOrFail();
        BibliotecaPrestamo::query()->create([
            'loan_code' => 'BIB-PRE-SUBCATEGORY-001',
            'borrower_type' => 'staff',
            'biblioteca_obra_id' => $obra->id,
            'biblioteca_ejemplar_id' => $copy->id,
            'borrower_name_snapshot' => 'Funcionaria de prueba',
            'borrowed_at' => now(),
            'due_at' => now()->addDays(7),
            'status' => 'activo',
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
        ]);

        $this->getJson('/api/biblioteca/dashboard')
            ->assertOk()
            ->assertJsonPath(
                'charts.subcategories_usage.0.label',
                'Narrativa · Novela histórica'
            )
            ->assertJsonPath('charts.subcategories_usage.0.total', 1);

        $this->deleteJson("/api/biblioteca/subcategorias/{$subcategory->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('subcategoria');
    }

    public function test_it_registers_a_material_with_individual_inventory_codes(): void
    {
        $locationId = (int) \DB::table('biblioteca_ubicaciones')->where('code', 'SALA-2')->value('id');

        $response = $this->postJson('/api/biblioteca/materiales', [
            'material_type' => 'kit_pedagogico',
            'title' => 'Kit de geometría',
            'subtitle' => 'Modelo aula 2026',
            'main_author' => 'Proveedor educativo',
            'biblioteca_ubicacion_id' => $locationId,
            'general_status' => 'disponible',
            'quantity' => 2,
            'ingress_date' => '2026-07-27',
            'origin' => 'compra',
            'physical_state' => 'bueno',
            'observations' => 'Incluye reglas, compás y transportador.',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.material_type', 'kit_pedagogico')
            ->assertJsonPath('data.total_copies', 2)
            ->assertJsonPath('data.available_copies', 2)
            ->assertJsonCount(2, 'data.ejemplares');

        $material = BibliotecaObra::query()->where('material_type', 'kit_pedagogico')->firstOrFail();
        $this->assertMatchesRegularExpression('/^BIB-MAT-2026-\d{4}$/', $material->internal_code);
        $this->assertSame(2, $material->ejemplares()->where('origin', 'compra')->count());
        $this->assertSame(2, $material->ejemplares()->where('physical_state', 'bueno')->count());
        $this->assertSame(2, $material->ejemplares()->where('biblioteca_ubicacion_id', $locationId)->count());
    }

    public function test_open_library_search_is_normalized_and_cached(): void
    {
        Cache::clear();
        Http::fake([
            'openlibrary.org/search.json*' => Http::response([
                'docs' => [[
                    'key' => '/works/OL45804W',
                    'title' => 'El principito',
                    'author_name' => ['Antoine de Saint-Exupéry'],
                    'first_publish_year' => 1943,
                    'isbn' => ['0156013983', '9780156013987'],
                    'publisher' => ['Reynal & Hitchcock'],
                    'language' => ['spa'],
                    'number_of_pages_median' => 96,
                    'cover_i' => 12345,
                    'subject' => ['Ficción', 'Clásicos'],
                    'edition_key' => ['OL7353617M'],
                ]],
            ]),
        ]);

        $service = app(OpenLibraryService::class);
        $first = $service->search('978-0-15-601398-7', 8);
        $second = $service->search('978-0-15-601398-7', 8);

        $this->assertSame($first, $second);
        $this->assertSame('El principito', $first[0]['title']);
        $this->assertSame('9780156013987', $first[0]['isbn']);
        $this->assertSame('Español', $first[0]['language']);
        $this->assertSame('/works/OL45804W', $first[0]['open_library_work_key']);
        Http::assertSentCount(1);
    }

    public function test_nt1_loan_records_the_guardian_as_pickup_person(): void
    {
        [$year, $level, $course, $student] = $this->academicContext('NT1');
        $student->forceFill([
            'guardian_name' => 'María Apoderada',
            'guardian_rut' => '12.345.678-5',
            'guardian_email' => 'maria@example.test',
            'guardian_relationship' => 'Madre',
        ])->save();
        $copy = $this->createAvailableCopy();

        $response = $this->postJson('/api/biblioteca/prestamos', [
            'borrower_type' => 'student',
            'student_profile_id' => $student->id,
            'biblioteca_ejemplar_id' => $copy->id,
            'borrowed_at' => '2026-07-27',
            'due_at' => '2026-08-03',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.pickup_person_type', 'guardian')
            ->assertJsonPath('data.pickup_person_name', 'María Apoderada')
            ->assertJsonPath('data.pickup_person_rut', '12.345.678-5');

        $loan = BibliotecaPrestamo::query()->firstOrFail();
        $this->assertSame('NT1 A', $loan->course_name_snapshot);
        $this->assertSame('student', $loan->borrower_estate);
        $this->assertSame('prestado', $copy->fresh()->availability_status);
    }

    public function test_catalogs_localize_borrowers_and_allow_a_registered_guardian_to_borrow(): void
    {
        [, , , $student] = $this->academicContext('4° básico');
        $student->forceFill([
            'guardian_name' => 'María Apoderada',
            'guardian_rut' => '12.345.678-5',
            'guardian_email' => 'maria@example.test',
            'guardian_relationship' => 'Madre',
        ])->save();
        $copy = $this->createAvailableCopy();
        $copy->obra->forceFill(['isbn' => '9789561234567'])->save();

        $catalogs = $this->getJson('/api/biblioteca/catalogs');
        $catalogs
            ->assertOk()
            ->assertJsonPath('borrower_types.0.label', 'Estudiante')
            ->assertJsonPath('borrower_types.1.label', 'Funcionario/a')
            ->assertJsonPath('borrower_types.2.label', 'Docente')
            ->assertJsonPath('borrower_types.3.label', 'Apoderado/a')
            ->assertJsonPath('reservation_requester_types.0.label', 'Estudiante')
            ->assertJsonPath('reservation_requester_types.1.label', 'Funcionario/a')
            ->assertJsonPath('reservation_requester_types.2.label', 'Docente')
            ->assertJsonPath('reservation_requester_types.3.label', 'Apoderado/a')
            ->assertJsonPath('reservation_requester_types.4.label', 'Curso')
            ->assertJsonPath('guardians.0.student_profile_id', $student->id)
            ->assertJsonPath('guardians.0.name', 'María Apoderada')
            ->assertJsonPath('exemplars.0.isbn', '9789561234567')
            ->assertJsonPath('exemplars.0.main_author', 'Autor de prueba');

        $this->postJson('/api/biblioteca/prestamos', [
            'borrower_type' => 'guardian',
            'student_profile_id' => $student->id,
            'biblioteca_ejemplar_id' => $copy->id,
            'borrowed_at' => '2026-07-27',
            'due_at' => '2026-08-03',
        ])
            ->assertCreated()
            ->assertJsonPath('data.borrower_name_snapshot', 'María Apoderada')
            ->assertJsonPath('data.borrower_rut_snapshot', '12.345.678-5')
            ->assertJsonPath('data.pickup_person_type', 'guardian');
    }

    public function test_textbook_order_reports_shortage_and_generates_student_roster(): void
    {
        [$year, $level, $course, $student] = $this->academicContext('4° básico');
        $libraryWorksBefore = BibliotecaObra::query()->count();

        $receptionResponse = $this->postJson('/api/biblioteca/textos-escolares/recepciones', [
            'received_at' => '2026-07-27',
            'source_name' => 'MINEDUC',
            'items' => [[
                'education_level_id' => $level->id,
                'title' => 'Matemática 4',
                'subject' => 'Matemática',
                'quantity_received' => 2,
            ]],
        ]);

        $receptionResponse
            ->assertCreated()
            ->assertJsonPath('data.items.0.texto_titulo.title', 'Matemática 4');

        $textbookTitle = BibliotecaTextoTitulo::query()->firstOrFail();
        $this->assertSame($libraryWorksBefore, BibliotecaObra::query()->count());
        $this->assertFalse(Schema::hasColumn('biblioteca_texto_recepcion_items', 'biblioteca_obra_id'));
        $this->assertFalse(Schema::hasColumn('biblioteca_texto_orden_items', 'biblioteca_obra_id'));

        $this->getJson('/api/biblioteca/textos-escolares')
            ->assertOk()
            ->assertJsonPath('titles.0.id', $textbookTitle->id)
            ->assertJsonPath('titles.0.available', 2);

        $orderResponse = $this->postJson('/api/biblioteca/textos-escolares/ordenes', [
            'academic_year_id' => $year->id,
            'education_level_id' => $level->id,
            'course_section_id' => $course->id,
            'prepared_at' => '2026-07-27',
            'items' => [[
                'biblioteca_texto_titulo_id' => $textbookTitle->id,
                'title' => 'Matemática 4',
                'subject' => 'Matemática',
                'quantity_required' => 3,
            ]],
        ]);

        $orderResponse
            ->assertCreated()
            ->assertJsonPath('data.items.0.quantity_available', 2)
            ->assertJsonPath('data.items.0.shortage_quantity', 1);

        $order = BibliotecaTextoOrden::query()->firstOrFail();
        $this->postJson("/api/biblioteca/textos-escolares/ordenes/{$order->id}/listado")
            ->assertOk()
            ->assertJsonPath('data.status', 'preparada')
            ->assertJsonPath('data.deliveries.0.student_profile_id', $student->id);

        $this->assertSame(1, BibliotecaTextoEntrega::query()->count());
    }

    public function test_it_emits_a_signed_pass_with_student_and_professor_data(): void
    {
        [, , , $student] = $this->academicContext('2° medio');
        $professor = Staff::query()->create([
            'full_name' => 'Profesora Responsable',
            'rut' => '10.111.222-3',
            'status' => 'activo',
            'active' => true,
        ]);

        $response = $this->postJson('/api/biblioteca/pases', [
            'student_profile_id' => $student->id,
            'professor_staff_id' => $professor->id,
            'valid_from' => '2026-07-27 10:15:00',
            'valid_until' => '2026-07-27 11:00:00',
            'reason' => 'Investigación para trabajo de Lenguaje.',
            'regulation_version' => 'Reglamento Biblioteca 2026',
            'signature_name' => $student->registered_name_resolved,
            'signature_rut' => $student->rut,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.student_name_snapshot', $student->registered_name_resolved)
            ->assertJsonPath('data.professor_name_snapshot', 'Profesora Responsable')
            ->assertJsonPath('data.status', 'emitido');

        $pass = BibliotecaPase::query()->firstOrFail();
        $this->assertMatchesRegularExpression('/^BIB-PAS-2026-\d{4}$/', $pass->pass_code);
        $this->assertNotNull($pass->signed_at);
    }

    public function test_external_space_request_requires_and_stores_contact_details(): void
    {
        $space = BibliotecaEspacio::query()->create([
            'name' => 'Sala 1 · Enseñanza Media',
            'location' => 'Biblioteca',
            'capacity' => 30,
            'resources' => [],
            'active' => true,
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
        ]);

        $this->postJson('/api/biblioteca/uso-espacios', [
            'biblioteca_espacio_id' => $space->id,
            'activity_type' => 'reunion',
            'title' => 'Encuentro cultural',
            'requester_type' => 'externo',
            'start_at' => '2026-07-28 10:00:00',
            'end_at' => '2026-07-28 11:00:00',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['external_name', 'external_email', 'external_institution']);

        $this->postJson('/api/biblioteca/uso-espacios', [
            'biblioteca_espacio_id' => $space->id,
            'activity_type' => 'reunion',
            'title' => 'Encuentro cultural',
            'requester_type' => 'externo',
            'external_name' => 'Ana Visitante',
            'external_email' => 'ana@example.test',
            'external_institution' => 'Biblioteca Comunal',
            'start_at' => '2026-07-28 10:00:00',
            'end_at' => '2026-07-28 11:00:00',
        ])->assertCreated();

        $this->assertDatabaseHas('biblioteca_uso_espacios', [
            'requester_type' => 'externo',
            'external_name' => 'Ana Visitante',
            'external_institution' => 'Biblioteca Comunal',
        ]);
    }

    /**
     * @return array{AcademicYear, EducationLevel, CourseSection, StudentProfile}
     */
    private function academicContext(string $levelName): array
    {
        $year = AcademicYear::query()->create([
            'name' => '2026',
            'year' => 2026,
            'starts_at' => '2026-03-01',
            'ends_at' => '2026-12-20',
            'is_active' => true,
            'is_closed' => false,
        ]);
        $level = EducationLevel::query()->create([
            'name' => $levelName,
            'order' => 1,
            'type' => str_contains(mb_strtolower($levelName), 'medio')
                ? 'media'
                : (str_starts_with($levelName, 'NT') ? 'parvularia' : 'basica'),
        ]);
        $course = CourseSection::query()->create([
            'academic_year_id' => $year->id,
            'education_level_id' => $level->id,
            'section_name' => 'A',
            'display_name' => "{$levelName} A",
            'capacity' => 40,
            'active' => true,
        ]);
        $student = StudentProfile::factory()->create([
            'first_name' => 'Josefina',
            'last_name' => 'Pérez',
            'registered_name' => null,
            'rut' => '21.222.333-4',
            'general_status' => 'activo',
        ]);
        StudentEnrollment::query()->create(array_merge([
            'student_profile_id' => $student->id,
            'academic_year_id' => $year->id,
            'course_section_id' => $course->id,
            'enrollment_status' => 'matriculada',
            'enrolled_at' => '2026-03-01',
        ], StudentEnrollment::snapshotPayload($year, $course)));

        return [$year, $level, $course, $student];
    }

    private function createAvailableCopy(): BibliotecaEjemplar
    {
        $obra = BibliotecaObra::query()->create([
            'material_type' => 'libro',
            'title' => 'Libro de préstamo',
            'main_author' => 'Autor de prueba',
            'internal_code' => 'BIB-OBR-TEST-001',
            'general_status' => 'disponible',
            'total_copies' => 1,
            'available_copies' => 1,
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
        ]);

        return BibliotecaEjemplar::query()->create([
            'biblioteca_obra_id' => $obra->id,
            'code' => 'BIB-EJ-TEST-001',
            'origin' => 'inventario_inicial',
            'physical_state' => 'bueno',
            'availability_status' => 'disponible',
            'registered_by' => $this->user->id,
            'is_active' => true,
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
        ]);
    }
}
