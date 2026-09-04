<?php

namespace Tests\Feature\Admin;

use App\Models\Cargo;
use App\Models\Role;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RelojControlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config([
            'services.geovictoria.api_key' => 'api-key-test',
            'services.geovictoria.api_secret' => 'api-secret-test',
            'services.geovictoria.base_url' => 'https://geo.test',
            'services.geovictoria.login_path' => '/api/v1/Login',
            'services.geovictoria.users_path' => '/api/v1/User/List',
            'services.geovictoria.attendance_book_path' => '/api/v1/AttendanceBook',
            'services.geovictoria.users_cache_minutes' => 10,
            'services.geovictoria.token_ttl_minutes' => 270,
        ]);
    }

    public function test_superadmin_loads_a_minimal_user_catalog_and_queries_the_attendance_book(): void
    {
        $staff = $this->institutionalStaff();

        Http::preventStrayRequests();
        Http::fake(function (Request $request) {
            return match ($request->url()) {
                'https://geo.test/api/v1/Login' => Http::response(['token' => 'jwt-test'], 200),
                'https://geo.test/api/v1/User/List' => Http::response($this->providerUsers(), 200),
                'https://geo.test/api/v1/AttendanceBook' => Http::response($this->attendanceBook(), 200),
                default => Http::response([], 404),
            };
        });

        Sanctum::actingAs($this->superAdmin());

        $this->getJson('/api/superadmin/reloj-control/users')
            ->assertOk()
            ->assertHeader('Cache-Control', 'must-revalidate, no-cache, no-store, private')
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('summary.total', 3)
            ->assertJsonPath('summary.active', 2)
            ->assertJsonPath('summary.inactive', 1)
            ->assertJsonPath('summary.linked', 2)
            ->assertJsonPath('summary.unlinked', 1)
            ->assertJsonPath('summary.provider_total', 3)
            ->assertJsonPath('summary.provider_only', 1)
            ->assertJsonPath('summary.provider_available', true)
            ->assertJsonPath('data.0.id', $staff['ana']->id)
            ->assertJsonPath('data.0.identifier', '11.111.111-1')
            ->assertJsonPath('data.0.name', 'Ana Institucional')
            ->assertJsonPath('data.0.geovictoria_linked', true)
            ->assertJsonPath('data.1.id', $staff['carla']->id)
            ->assertJsonPath('data.1.geovictoria_linked', false)
            ->assertJsonMissing(['name' => 'Persona Ajena'])
            ->assertJsonMissingPath('data.0.address')
            ->assertJsonMissingPath('data.0.phone')
            ->assertJsonMissingPath('data.0.provider_identifier');

        $this->postJson('/api/superadmin/reloj-control/attendance', [
            'date_from' => '2026-08-26',
            'date_to' => '2026-08-28',
            'staff_ids' => [$staff['ana']->id],
        ])
            ->assertOk()
            ->assertJsonPath('summary.requested_users', 1)
            ->assertJsonPath('summary.returned_users', 1)
            ->assertJsonPath('summary.days', 3)
            ->assertJsonPath('summary.punches', 4)
            ->assertJsonPath('summary.absences', 1)
            ->assertJsonPath('summary.worked_minutes', 960)
            ->assertJsonPath('summary.worked_time_label', '16 h')
            ->assertJsonPath('summary.entry_early_minutes', 7)
            ->assertJsonPath('summary.entry_late_minutes', 4)
            ->assertJsonPath('summary.exit_early_minutes', 10)
            ->assertJsonPath('summary.exit_after_minutes', 5)
            ->assertJsonPath('data.0.user.id', $staff['ana']->id)
            ->assertJsonPath('data.0.user.identifier', '11.111.111-1')
            ->assertJsonPath('data.0.user.name', 'Ana Institucional')
            ->assertJsonPath('data.0.shifts.0.name', 'Administrativo')
            ->assertJsonPath('data.0.schedule.start_at', '2026-08-28T08:00:00-04:00')
            ->assertJsonPath('data.0.schedule.end_at', '2026-08-28T16:15:00-04:00')
            ->assertJsonPath('data.0.attendance_variance.entry_at', '2026-08-28T07:53:00-04:00')
            ->assertJsonPath('data.0.attendance_variance.exit_at', '2026-08-28T16:20:00-04:00')
            ->assertJsonPath('data.0.attendance_variance.entry_delta_minutes', -7)
            ->assertJsonPath('data.0.attendance_variance.exit_delta_minutes', 5)
            ->assertJsonPath('data.0.attendance_variance.entry_label', '7 min antes')
            ->assertJsonPath('data.0.attendance_variance.exit_label', '5 min después')
            ->assertJsonPath('data.1.attendance_variance.entry_delta_minutes', 4)
            ->assertJsonPath('data.1.attendance_variance.exit_delta_minutes', -10)
            ->assertJsonPath('data.2.absent', true)
            ->assertJsonPath('weekly.0.week_start', '2026-08-24')
            ->assertJsonPath('weekly.0.week_end', '2026-08-30')
            ->assertJsonPath('weekly.0.user.id', $staff['ana']->id)
            ->assertJsonPath('weekly.0.days', 3)
            ->assertJsonPath('weekly.0.worked_days', 2)
            ->assertJsonPath('weekly.0.absences', 1)
            ->assertJsonPath('weekly.0.worked_minutes', 960)
            ->assertJsonPath('weekly.0.worked_time_label', '16 h')
            ->assertJsonPath('weekly.0.entry_early_minutes', 7)
            ->assertJsonPath('weekly.0.entry_late_minutes', 4)
            ->assertJsonPath('weekly.0.exit_early_minutes', 10)
            ->assertJsonPath('weekly.0.exit_after_minutes', 5);

        $this->postJson('/api/superadmin/reloj-control/reports', [
            'date_from' => '2026-08-26',
            'date_to' => '2026-08-28',
            'scope' => 'all_linked',
            'tolerance_minutes' => 0,
        ])
            ->assertOk()
            ->assertHeader('Cache-Control', 'must-revalidate, no-cache, no-store, private')
            ->assertJsonPath('summary.requested_users', 2)
            ->assertJsonPath('summary.returned_users', 1)
            ->assertJsonPath('summary.scheduled_days', 2)
            ->assertJsonPath('summary.worked_days', 2)
            ->assertJsonPath('summary.tardy_people', 1)
            ->assertJsonPath('summary.tardiness_occurrences', 1)
            ->assertJsonPath('summary.tardiness_minutes', 4)
            ->assertJsonPath('summary.entry_early_minutes', 7)
            ->assertJsonPath('summary.exit_early_minutes', 10)
            ->assertJsonPath('summary.exit_after_minutes', 5)
            ->assertJsonPath('summary.absent_people', 1)
            ->assertJsonPath('summary.absences', 1)
            ->assertJsonPath('summary.absences_without_justification', 1)
            ->assertJsonPath('reports.tardiness.0.user.id', $staff['ana']->id)
            ->assertJsonPath('reports.tardiness.0.occurrences', 1)
            ->assertJsonPath('reports.tardiness.0.minutes', 4)
            ->assertJsonPath('reports.balances.0.net_minutes', -2)
            ->assertJsonPath('reports.balances.0.net_label', '-2 min')
            ->assertJsonPath('reports.absences.0.user.id', $staff['ana']->id)
            ->assertJsonPath('reports.absences.0.occurrences', 1)
            ->assertJsonPath('reports.absences.0.details.0.date', '20260826000000')
            ->assertJsonPath('reports.by_group.0.name', 'Administración')
            ->assertJsonPath('coverage.batches', 1)
            ->assertJsonPath('filters.scope', 'all_linked')
            ->assertJsonPath('filters.tolerance_minutes', 0);

        $this->postJson('/api/superadmin/reloj-control/attendance', [
            'date_from' => '2026-08-27',
            'date_to' => '2026-08-28',
            'staff_ids' => [$staff['carla']->id],
        ])->assertUnprocessable()->assertJsonValidationErrors('staff_ids');

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://geo.test/api/v1/Login'
                && $request['User'] === 'api-key-test'
                && $request['Password'] === 'api-secret-test';
        });

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://geo.test/api/v1/AttendanceBook'
                && $request->hasHeader('Authorization', 'Bearer jwt-test')
                && $request['StartDate'] === '20260826000000'
                && $request['EndDate'] === '20260828235959'
                && $request['UserIds'] === '11111111-1';
        });

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://geo.test/api/v1/AttendanceBook'
                && $request['StartDate'] === '20260826000000'
                && $request['EndDate'] === '20260828235959'
                && $request['UserIds'] === '11111111-1,22222222-2';
        });
    }

    public function test_reloj_control_is_exclusive_to_active_superadmins(): void
    {
        Http::preventStrayRequests();

        $this->getJson('/api/superadmin/reloj-control/users')->assertUnauthorized();

        $regularUser = User::factory()->create(['active' => true]);
        $regularRole = Role::query()->create([
            'slug' => 'administrador_reloj_control_test',
            'name' => 'Administrador de prueba',
            'active' => true,
        ]);
        $regularUser->roles()->attach($regularRole);
        Sanctum::actingAs($regularUser);
        $this->getJson('/api/superadmin/reloj-control/users')->assertForbidden();

        Sanctum::actingAs($this->superAdmin(active: false));
        $this->postJson('/api/superadmin/reloj-control/attendance', [
            'date_from' => '2026-08-27',
            'date_to' => '2026-08-28',
            'staff_ids' => [1],
        ])->assertForbidden();

        $this->postJson('/api/superadmin/reloj-control/reports', [
            'date_from' => '2026-08-27',
            'date_to' => '2026-08-28',
            'scope' => 'all_linked',
        ])->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_attendance_query_rejects_unsafe_or_excessive_ranges_before_calling_geovictoria(): void
    {
        $staff = Staff::query()->create([
            'full_name' => 'Funcionario Seguro',
            'rut' => '11.111.111-1',
            'status' => 'activo',
            'active' => true,
        ]);

        Http::preventStrayRequests();
        Sanctum::actingAs($this->superAdmin());

        $this->postJson('/api/superadmin/reloj-control/attendance', [
            'date_from' => '2026-07-01',
            'date_to' => '2026-08-28',
            'staff_ids' => [$staff->id],
        ])->assertUnprocessable()->assertJsonValidationErrors('date_to');

        $this->postJson('/api/superadmin/reloj-control/attendance', [
            'date_from' => '2026-08-27',
            'date_to' => '2026-08-28',
            'staff_ids' => [$staff->id, 'valor no permitido'],
        ])->assertUnprocessable()->assertJsonValidationErrors('staff_ids.1');

        $this->postJson('/api/superadmin/reloj-control/reports', [
            'date_from' => '2026-07-01',
            'date_to' => '2026-08-28',
            'scope' => 'all_linked',
        ])->assertUnprocessable()->assertJsonValidationErrors('date_to');

        $this->postJson('/api/superadmin/reloj-control/reports', [
            'date_from' => '2026-08-27',
            'date_to' => '2026-08-28',
            'scope' => 'selected',
        ])->assertUnprocessable()->assertJsonValidationErrors('staff_ids');

        Http::assertNothingSent();
    }

    public function test_local_staff_remain_visible_when_geovictoria_rejects_the_credentials(): void
    {
        $staff = Staff::query()->create([
            'full_name' => 'Funcionaria Local',
            'rut' => '12.345.678-5',
            'status' => 'activo',
            'active' => true,
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'https://geo.test/api/v1/Login' => Http::response(['message' => 'Unauthorized'], 401),
        ]);
        Sanctum::actingAs($this->superAdmin());

        $this->getJson('/api/superadmin/reloj-control/users')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $staff->id)
            ->assertJsonPath('data.0.geovictoria_linked', false)
            ->assertJsonPath('summary.total', 1)
            ->assertJsonPath('summary.linked', 0)
            ->assertJsonPath('summary.unlinked', 1)
            ->assertJsonPath('summary.provider_available', false)
            ->assertJsonPath('summary.provider_status', 401);
    }

    public function test_general_report_batches_all_linked_staff_without_exposing_the_frontend_limit(): void
    {
        $providerUsers = [];

        foreach (range(1, 41) as $index) {
            $identifier = sprintf('50000%03d-%d', $index, $index % 10);
            Staff::query()->create([
                'full_name' => sprintf('Funcionario Reporte %02d', $index),
                'rut' => $identifier,
                'status' => 'activo',
                'active' => true,
            ]);
            $providerUsers[] = [
                'Identifier' => $identifier,
                'Name' => 'Funcionario',
                'LastName' => sprintf('Reporte %02d', $index),
                'Enabled' => 1,
            ];
        }

        $attendanceCalls = 0;
        Http::preventStrayRequests();
        Http::fake(function (Request $request) use (&$attendanceCalls, $providerUsers) {
            return match ($request->url()) {
                'https://geo.test/api/v1/Login' => Http::response(['token' => 'jwt-report'], 200),
                'https://geo.test/api/v1/User/List' => Http::response($providerUsers, 200),
                'https://geo.test/api/v1/AttendanceBook' => tap(Http::response(['Users' => []], 200), function () use (&$attendanceCalls): void {
                    $attendanceCalls++;
                }),
                default => Http::response([], 404),
            };
        });
        Sanctum::actingAs($this->superAdmin());

        $this->postJson('/api/superadmin/reloj-control/reports', [
            'date_from' => '2026-08-24',
            'date_to' => '2026-08-30',
            'scope' => 'all_linked',
            'tolerance_minutes' => 5,
        ])
            ->assertOk()
            ->assertJsonPath('summary.requested_users', 41)
            ->assertJsonPath('summary.returned_users', 0)
            ->assertJsonPath('coverage.batches', 2)
            ->assertJsonPath('filters.tolerance_minutes', 5);

        $this->assertSame(2, $attendanceCalls);
    }

    public function test_superadmin_navigation_exposes_reloj_control_under_its_private_parent(): void
    {
        Sanctum::actingAs($this->superAdmin());

        $modules = collect($this->getJson('/api/me/modules')->assertOk()->json('data'));
        $parent = $modules->firstWhere('slug', 'superadmin');
        $module = $modules->firstWhere('slug', 'superadmin_reloj_control');

        $this->assertNotNull($parent);
        $this->assertNotNull($module);
        $this->assertSame('/superadmin/reloj-control', $module['frontend_route']);
        $this->assertSame($parent['id'], $module['parent_id']);
    }

    private function superAdmin(bool $active = true): User
    {
        $user = User::factory()->create(['active' => $active]);
        $role = Role::query()->firstOrCreate(
            ['slug' => 'super_admin'],
            ['name' => 'Super Admin', 'active' => true],
        );
        $user->roles()->attach($role);

        return $user;
    }

    /** @return array{ana: Staff, bruno: Staff, carla: Staff} */
    private function institutionalStaff(): array
    {
        $administration = Cargo::query()->create([
            'name' => 'Administración',
            'slug' => 'administracion-reloj-control-test',
            'active' => true,
        ]);

        return [
            'ana' => Staff::query()->create([
                'full_name' => 'Ana Institucional',
                'rut' => '11.111.111-1',
                'institutional_email' => 'ana@institucion.test',
                'cargo_id' => $administration->id,
                'status' => 'activo',
                'active' => true,
            ]),
            'bruno' => Staff::query()->create([
                'full_name' => 'Bruno Institucional',
                'rut' => '22.222.222-2',
                'status' => 'inactivo',
                'active' => false,
            ]),
            'carla' => Staff::query()->create([
                'full_name' => 'Carla Local',
                'rut' => '33.333.333-3',
                'status' => 'activo',
                'active' => true,
            ]),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function providerUsers(): array
    {
        return [
            [
                'Identifier' => '11111111-1',
                'Name' => 'Nombre',
                'LastName' => 'Proveedor',
                'Email' => 'ana@example.test',
                'Adress' => 'Dato que no debe exponerse',
                'Phone' => '999999999',
                'Enabled' => 1,
                'GroupDescription' => 'Administración',
                'positionName' => 'Secretaria',
            ],
            [
                'Identifier' => '22222222-2',
                'Name' => 'Bruno',
                'LastName' => 'Inactivo',
                'Email' => 'bruno@example.test',
                'Enabled' => 0,
                'GroupDescription' => 'Servicios',
            ],
            [
                'Identifier' => '44444444-4',
                'Name' => 'Persona',
                'LastName' => 'Ajena',
                'Email' => 'ajena@example.test',
                'Enabled' => 1,
                'GroupDescription' => 'Empresa externa',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function attendanceBook(): array
    {
        return [
            'Users' => [[
                'Identifier' => '11111111-1',
                'Name' => 'Nombre',
                'LastName' => 'Proveedor',
                'Email' => 'ana@example.test',
                'Enabled' => 1,
                'GroupDescription' => 'Administración',
                'WorkedDays' => 2,
                'DaysAttended' => 2,
                'Absences' => 1,
                'TotalWorkedHours' => '08:15',
                'PlannedInterval' => [
                    [
                        'Date' => '20260828000000',
                        'WorkedHours' => '08:15',
                        'NonWorkedHours' => '00:00',
                        'Absent' => false,
                        'Holiday' => false,
                        'Worked' => true,
                        'Punches' => [
                            ['Type' => 'Ingreso', 'Date' => '20260828075300', 'Origin' => 'Reloj control'],
                            ['Type' => 'Salida', 'Date' => '20260828162000', 'Origin' => 'Reloj control'],
                        ],
                        'Shifts' => [
                            [
                                'ShiftDisplay' => 'Administrativo',
                                'StartTime' => '08:00',
                                'ExitTime' => '16:15',
                                'Delay' => '00:00',
                            ],
                            [
                                'ShiftDisplay' => 'Break',
                                'StartTime' => '00:00',
                                'ExitTime' => '00:00',
                                'Delay' => '00:00',
                            ],
                        ],
                        'TimeOffs' => [],
                    ],
                    [
                        'Date' => '20260827000000',
                        'WorkedHours' => '07:45',
                        'NonWorkedHours' => '00:15',
                        'Absent' => false,
                        'Holiday' => false,
                        'Worked' => true,
                        'Punches' => [
                            ['Type' => 'Ingreso', 'Date' => '20260827080400', 'Origin' => 'Reloj control'],
                            ['Type' => 'Salida', 'Date' => '20260827155000', 'Origin' => 'Reloj control'],
                        ],
                        'Shifts' => [[
                            'ShiftDisplay' => 'Administrativo',
                            'StartTime' => '08:00',
                            'ExitTime' => '16:00',
                            'Delay' => '00:04',
                            'EarlyLeave' => '00:10',
                        ]],
                        'TimeOffs' => [],
                    ],
                    [
                        'Date' => '20260826000000',
                        'WorkedHours' => '00:00',
                        'NonWorkedHours' => '08:00',
                        'Absent' => true,
                        'Holiday' => false,
                        'Worked' => false,
                        'Punches' => [],
                        'Shifts' => [],
                        'TimeOffs' => [],
                    ],
                ],
            ]],
            'ExtraTimeValues' => [],
        ];
    }
}
