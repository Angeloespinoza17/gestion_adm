<?php

namespace Tests\Feature\Maintenance;

use App\Models\MaintenanceWorkOrder;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MaintenanceWorkOrderPhotoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Sanctum::actingAs($this->authorizedUser('crear_ot'));
    }

    public function test_supported_upload_formats_are_normalized_to_jpeg(): void
    {
        foreach (['jpg', 'png', 'gif', 'bmp', 'webp'] as $format) {
            $response = $this->withHeader('Accept', 'application/json')->post(
                '/api/maintenance/work-orders',
                $this->validPayload([
                    'description' => "Foto de prueba {$format}.",
                    'photo' => $this->fakeImage($format),
                ])
            );

            $response->assertCreated();

            $path = $response->json('data.photo_reference');
            $this->assertIsString($path);
            $this->assertStringStartsWith('maintenance/work-orders/', $path);
            $this->assertStringEndsWith('.jpg', $path);
            Storage::disk('public')->assertExists($path);

            $imageInfo = getimagesizefromstring(Storage::disk('public')->get($path));
            $this->assertIsArray($imageInfo);
            $this->assertSame(IMAGETYPE_JPEG, $imageInfo[2]);
        }
    }

    public function test_invalid_image_content_is_rejected(): void
    {
        $workOrderCount = MaintenanceWorkOrder::query()->count();

        $response = $this->withHeader('Accept', 'application/json')->post(
            '/api/maintenance/work-orders',
            $this->validPayload([
                'photo' => UploadedFile::fake()->createWithContent('foto.jpg', '<html>no es una imagen</html>'),
            ])
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('photo');

        $this->assertDatabaseCount('maintenance_work_orders', $workOrderCount);
        Storage::disk('public')->assertDirectoryEmpty('maintenance/work-orders');
    }

    public function test_photo_url_distinguishes_managed_external_and_legacy_references(): void
    {
        $managed = new MaintenanceWorkOrder([
            'photo_reference' => 'maintenance/work-orders/foto.jpg',
        ]);
        $external = new MaintenanceWorkOrder([
            'photo_reference' => 'https://drive.google.com/file/d/example/view',
        ]);
        $legacy = new MaintenanceWorkOrder([
            'photo_reference' => 'foto histórica.HEIC',
        ]);

        $this->assertSame('/storage/maintenance/work-orders/foto.jpg', $managed->photo_url);
        $this->assertSame('https://drive.google.com/file/d/example/view', $external->photo_url);
        $this->assertNull($legacy->photo_url);
        $this->assertTrue(MaintenanceWorkOrder::isManagedPhotoReference('maintenance/work-orders/foto.jpg'));
        $this->assertFalse(MaintenanceWorkOrder::isManagedPhotoReference('maintenance/work-orders/../privado.txt'));
        $this->assertFalse(MaintenanceWorkOrder::isManagedPhotoReference('https://example.com/foto.jpg'));
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'priority' => 'Media',
            'status' => 'Sin comenzar',
            'description' => 'Orden de trabajo con fotografía.',
        ], $overrides);
    }

    private function fakeImage(string $format): UploadedFile
    {
        $image = imagecreatetruecolor(64, 48);
        $background = imagecolorallocate($image, 49, 82, 201);
        $foreground = imagecolorallocate($image, 255, 255, 255);
        imagefill($image, 0, 0, $background);
        imagefilledrectangle($image, 12, 10, 52, 38, $foreground);

        ob_start();
        match ($format) {
            'jpg' => imagejpeg($image, null, 90),
            'png' => imagepng($image),
            'gif' => imagegif($image),
            'bmp' => imagebmp($image),
            'webp' => imagewebp($image, null, 90),
        };
        $contents = ob_get_clean();
        imagedestroy($image);

        return UploadedFile::fake()->createWithContent("foto.{$format}", $contents);
    }

    private function authorizedUser(string $permissionSlug): User
    {
        $user = User::factory()->create(['active' => true, 'user_type' => 'staff']);
        $role = Role::query()->create([
            'name' => 'Mantención fotos',
            'slug' => 'mantencion-fotos',
            'active' => true,
        ]);
        $permission = Permission::query()->firstOrCreate(
            ['slug' => $permissionSlug],
            ['name' => 'Gestionar fotos de mantención', 'active' => true]
        );
        $role->permissions()->attach($permission);
        $user->roles()->attach($role);

        return $user;
    }
}
