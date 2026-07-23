<?php

namespace Tests\Feature\Staff;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Staff;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use ZipArchive;

class StaffImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_csv_rows_and_allows_optional_fields_to_be_empty(): void
    {
        Sanctum::actingAs($this->userWithPermissions(['gestionar_funcionarios']));

        $csv = implode("\n", [
            'nombre_completo;rut;correo_institucional;telefono;estado;activo',
            'Andrea Importada;12.345.678-5;andrea.importada@cnscgestion.local;;activo;Sí',
            'Funcionario Solo Nombre;;;;activo;Sí',
        ]);

        $response = $this->post('/api/staff/import', [
            'file' => UploadedFile::fake()->createWithContent('funcionarios.csv', $csv),
            'update_existing' => true,
        ], ['Accept' => 'application/json']);

        $response
            ->assertOk()
            ->assertJsonPath('data.processed', 2)
            ->assertJsonPath('data.created', 2)
            ->assertJsonPath('data.updated', 0)
            ->assertJsonPath('data.skipped', 0)
            ->assertJsonPath('data.error_count', 0);

        $withAccount = Staff::query()->where('rut', '12345678-5')->firstOrFail();
        $withoutAccount = Staff::query()->where('full_name', 'Funcionario Solo Nombre')->firstOrFail();

        $this->assertSame('andrea.importada@cnscgestion.local', $withAccount->institutional_email);
        $this->assertDatabaseHas('users', ['staff_id' => $withAccount->id, 'email' => 'andrea.importada@cnscgestion.local']);
        $this->assertNull($withoutAccount->rut);
        $this->assertNull($withoutAccount->institutional_email);
        $this->assertFalse(User::query()->where('staff_id', $withoutAccount->id)->exists());
    }

    public function test_it_downloads_the_xlsx_import_template(): void
    {
        Sanctum::actingAs($this->userWithPermissions(['gestionar_funcionarios']));

        $response = $this->get('/api/staff/import-template');

        $response
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->assertStringStartsWith('PK', $response->streamedContent());
    }

    public function test_it_imports_a_row_from_the_downloadable_xlsx_template(): void
    {
        Sanctum::actingAs($this->userWithPermissions(['gestionar_funcionarios']));

        $path = $this->xlsxFixture([
            'A5' => 'Funcionario desde XLSX',
            'B5' => '11.111.111-1',
            'D5' => 'xlsx.importado@cnscgestion.local',
            'O5' => 'activo',
            'V5' => 'Sí',
        ]);

        try {
            $response = $this->post('/api/staff/import', [
                'file' => new UploadedFile(
                    $path,
                    'plantilla_importacion_funcionarios.xlsx',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    null,
                    true,
                ),
                'update_existing' => true,
            ], ['Accept' => 'application/json']);

            $response
                ->assertOk()
                ->assertJsonPath('data.processed', 1)
                ->assertJsonPath('data.created', 1)
                ->assertJsonPath('data.skipped', 0)
                ->assertJsonPath('data.error_count', 0);

            $staff = Staff::query()->where('rut', '11111111-1')->firstOrFail();
            $this->assertSame('Funcionario desde XLSX', $staff->full_name);
            $this->assertDatabaseHas('users', [
                'staff_id' => $staff->id,
                'email' => 'xlsx.importado@cnscgestion.local',
            ]);
        } finally {
            @unlink($path);
        }
    }

    /** @param array<string, string> $values */
    private function xlsxFixture(array $values): string
    {
        $template = public_path('templates/plantilla_importacion_funcionarios.xlsx');
        $path = tempnam(sys_get_temp_dir(), 'staff-xlsx-');
        $this->assertNotFalse($path);
        $this->assertTrue(copy($template, $path));

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($path) === true);
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertNotFalse($sheetXml);

        $document = new DOMDocument();
        $this->assertTrue($document->loadXML($sheetXml));
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        foreach ($values as $reference => $value) {
            $cell = $xpath->query("//x:c[@r='{$reference}']")?->item(0);
            $this->assertNotNull($cell, "No se encontró la celda {$reference} en la plantilla.");
            while ($cell->firstChild) {
                $cell->removeChild($cell->firstChild);
            }
            $cell->setAttribute('t', 'str');
            $cell->appendChild($document->createElementNS(
                'http://schemas.openxmlformats.org/spreadsheetml/2006/main',
                'x:v',
                $value,
            ));
        }

        $zip->addFromString('xl/worksheets/sheet1.xml', $document->saveXML());
        $zip->close();

        return $path;
    }

    private function userWithPermissions(array $permissionSlugs): User
    {
        $role = Role::query()->create([
            'name' => 'Rol '.Str::random(8),
            'slug' => 'rol_'.Str::random(12),
            'active' => true,
        ]);

        $permissions = collect($permissionSlugs)->map(fn (string $slug) => Permission::query()->firstOrCreate(
            ['slug' => $slug],
            ['name' => Str::headline(str_replace('_', ' ', $slug)), 'active' => true],
        ));

        $role->permissions()->sync($permissions->pluck('id')->all());

        $user = User::factory()->create(['active' => true, 'user_type' => 'staff']);
        $user->roles()->attach($role);

        return $user;
    }
}
