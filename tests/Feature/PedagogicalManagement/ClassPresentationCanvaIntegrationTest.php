<?php

namespace Tests\Feature\PedagogicalManagement;

use App\Enums\PedagogicalManagement\CanvaConnectionStatus;
use App\Enums\PedagogicalManagement\CanvaPublicationStatus;
use App\Enums\PedagogicalManagement\ClassPresentationStatus;
use App\Jobs\PedagogicalManagement\PublishClassPresentationToCanvaJob;
use App\Models\AcademicYear;
use App\Models\CourseSection;
use App\Models\EducationLevel;
use App\Models\LibroDigital\CurriculumCatalog;
use App\Models\LibroDigital\CurriculumProgram;
use App\Models\LibroDigital\CurriculumUnit;
use App\Models\LibroDigital\CurriculumVersion;
use App\Models\LibroDigital\School;
use App\Models\PedagogicalManagement\CanvaConnection;
use App\Models\PedagogicalManagement\CanvaOAuthState;
use App\Models\PedagogicalManagement\ClassPresentation;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Schedule\ScheduleSubject;
use App\Models\User;
use App\Services\PedagogicalManagement\ClassPresentations\Canva\CanvaTemplateFieldMapper;
use App\Services\PedagogicalManagement\ClassPresentations\Canva\CanvaTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ClassPresentationCanvaIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config()->set([
            'canva.enabled' => true,
            'canva.client_id' => 'canva-client-test',
            'canva.client_secret' => 'canva-secret-test',
            'canva.authorize_url' => 'https://www.canva.com/api/oauth/authorize',
            'canva.base_url' => 'https://api.canva.test/rest/v1',
            'canva.redirect_uri' => 'http://localhost/api/integraciones/canva/callback',
            'canva.scopes' => ['design:content:write', 'design:meta:read', 'brandtemplate:meta:read', 'brandtemplate:content:read', 'profile:read'],
            'canva.autofill_trial_enabled' => false,
        ]);
    }

    public function test_oauth_authorization_uses_pkce_and_stores_only_the_hashed_state_and_encrypted_verifier(): void
    {
        [$user, $school] = $this->userAndSchool();

        $response = $this->actingAs($user)->postJson('/api/gestion-pedagogica/canva/autorizacion', [
            'school_id' => $school->id,
            'redirect_to' => '/gestion-pedagogica/generador-clases',
        ]);

        $response->assertAccepted();
        $authorizationUrl = (string) $response->json('data.authorization_url');
        parse_str((string) parse_url($authorizationUrl, PHP_URL_QUERY), $query);
        $this->assertSame('S256', $query['code_challenge_method'] ?? null);
        $this->assertSame('code', $query['response_type'] ?? null);
        $this->assertNotEmpty($query['code_challenge'] ?? null);
        $this->assertNotEmpty($query['state'] ?? null);
        $this->assertStringNotContainsString('canva-secret-test', $authorizationUrl);

        $state = CanvaOAuthState::query()->firstOrFail();
        $this->assertSame(hash('sha256', (string) $query['state']), $state->state_hash);
        $this->assertNotEmpty($state->code_verifier_encrypted);
        $rawVerifier = DB::table('canva_oauth_states')->where('id', $state->id)->value('code_verifier_encrypted');
        $this->assertNotSame($state->code_verifier_encrypted, $rawVerifier);
        $this->assertDatabaseMissing('canva_oauth_states', ['state_hash' => (string) $query['state']]);
    }

    public function test_oauth_callback_encrypts_rotating_tokens_and_connection_response_never_exposes_them(): void
    {
        [$user, $school] = $this->userAndSchool();
        $authorization = $this->actingAs($user)->postJson('/api/gestion-pedagogica/canva/autorizacion', [
            'school_id' => $school->id,
        ])->assertAccepted();
        parse_str((string) parse_url((string) $authorization->json('data.authorization_url'), PHP_URL_QUERY), $query);

        Http::fake([
            'https://api.canva.test/rest/v1/oauth/token' => Http::response([
                'access_token' => 'access-token-test',
                'refresh_token' => 'refresh-token-test',
                'expires_in' => 3600,
                'scope' => 'design:content:write design:meta:read brandtemplate:meta:read brandtemplate:content:read profile:read',
            ]),
            'https://api.canva.test/rest/v1/users/me' => Http::response(['team_user' => ['user_id' => 'canva-user-1', 'team_id' => 'canva-team-1']]),
            'https://api.canva.test/rest/v1/users/me/profile' => Http::response(['profile' => ['display_name' => 'Docente Canva']]),
            'https://api.canva.test/rest/v1/users/me/capabilities' => Http::response(['capabilities' => ['brand_template', 'autofill']]),
        ]);

        $this->get('/api/integraciones/canva/callback?'.http_build_query([
            'state' => $query['state'],
            'code' => 'authorization-code-test',
        ]))->assertRedirectContains('canva=connected');

        $connection = CanvaConnection::query()->firstOrFail();
        $this->assertSame('access-token-test', $connection->access_token_encrypted);
        $this->assertSame('refresh-token-test', $connection->refresh_token_encrypted);
        $this->assertNotSame('access-token-test', DB::table('canva_connections')->where('id', $connection->id)->value('access_token_encrypted'));
        $this->assertNotSame('refresh-token-test', DB::table('canva_connections')->where('id', $connection->id)->value('refresh_token_encrypted'));

        $response = $this->actingAs($user)->getJson('/api/gestion-pedagogica/canva/conexion?school_id='.$school->id);
        $response->assertOk()
            ->assertJsonPath('data.configured', true)
            ->assertJsonPath('data.connected', true)
            ->assertJsonPath('data.capabilities.autofill', true)
            ->assertJsonPath('data.capabilities.brand_template', true);
        $this->assertStringNotContainsString('access-token-test', $response->getContent());
        $this->assertStringNotContainsString('refresh-token-test', $response->getContent());
        Http::assertSent(fn (HttpRequest $request): bool => $request->url() === 'https://api.canva.test/rest/v1/oauth/token'
            && $request['code_verifier'] !== null
            && str_starts_with((string) $request->header('Authorization')[0], 'Basic '));
    }

    public function test_oauth_callback_logs_invalid_client_without_recording_oauth_secrets(): void
    {
        [$user, $school] = $this->userAndSchool();
        $authorization = $this->actingAs($user)->postJson('/api/gestion-pedagogica/canva/autorizacion', [
            'school_id' => $school->id,
        ])->assertAccepted();
        parse_str((string) parse_url((string) $authorization->json('data.authorization_url'), PHP_URL_QUERY), $query);

        Http::fake([
            'https://api.canva.test/rest/v1/oauth/token' => Http::response([
                'code' => 'invalid_client',
                'message' => 'Invalid client credentials.',
            ], 400),
        ]);
        Log::spy();

        $authorizationCode = 'authorization-code-must-not-be-logged';
        $response = $this->get('/api/integraciones/canva/callback?'.http_build_query([
            'state' => $query['state'],
            'code' => $authorizationCode,
        ]));

        $response->assertRedirectContains('canva=error')
            ->assertRedirectContains('code=CANVA_INVALID_CLIENT');
        $this->assertDatabaseCount('canva_connections', 0);
        $this->assertNotNull(CanvaOAuthState::query()->firstOrFail()->consumed_at);
        Log::shouldHaveReceived('warning')->once()->with('Canva OAuth callback failed.', [
            'provider' => 'canva',
            'operation' => 'oauth_callback',
            'failure_code' => 'CANVA_INVALID_CLIENT',
            'http_status' => 400,
            'retryable' => false,
        ]);
    }

    public function test_templates_are_scoped_to_the_connected_user_and_strictly_validated_before_selection(): void
    {
        [$user, $school] = $this->userAndSchool();
        $this->connection($user, $school);
        $fields = array_fill_keys(app(CanvaTemplateFieldMapper::class)->expectedFields(12), ['type' => 'text']);
        Http::fake([
            'https://api.canva.test/rest/v1/brand-templates*' => Http::sequence()
                ->push(['items' => [[
                    'id' => 'template_12_slides',
                    'title' => 'Institucional 12 páginas',
                    'thumbnail' => ['url' => 'https://document-export.canva.com/template-preview.png', 'width' => 640, 'height' => 360],
                ]], 'continuation' => 'next-page'])
                ->push(['dataset' => $fields]),
        ]);

        $this->actingAs($user)->getJson('/api/gestion-pedagogica/canva/plantillas?'.http_build_query([
            'school_id' => $school->id,
            'limit' => 24,
        ]))->assertOk()
            ->assertJsonPath('data.items.0.id', 'template_12_slides')
            ->assertJsonPath('data.items.0.title', 'Institucional 12 páginas')
            ->assertJsonPath('data.continuation', 'next-page');

        $this->actingAs($user)->postJson('/api/gestion-pedagogica/canva/plantillas/template_12_slides/validar', [
            'school_id' => $school->id,
            'slide_count' => 12,
        ])->assertOk()
            ->assertJsonPath('data.compatible', true)
            ->assertJsonPath('data.missing_fields', [])
            ->assertJsonPath('data.invalid_type_fields', []);

        Http::assertSent(fn (HttpRequest $request): bool => $request->method() === 'GET'
            && str_contains($request->url(), '/brand-templates?')
            && str_contains($request->url(), 'dataset=non_empty'));
    }

    public function test_brand_template_account_remains_strict_when_development_trial_is_disabled(): void
    {
        [$user, $school] = $this->userAndSchool();
        $this->connection($user, $school, ['brand_template', 'resize', 'export_png_transparency']);
        Http::fake();

        $this->actingAs($user)->getJson('/api/gestion-pedagogica/canva/conexion?school_id='.$school->id)
            ->assertOk()
            ->assertJsonPath('data.connected', true)
            ->assertJsonPath('data.enterprise_autofill', false)
            ->assertJsonPath('data.trial_enabled', false)
            ->assertJsonPath('data.mode', 'unavailable')
            ->assertJsonPath('data.autofill_available', false)
            ->assertJsonPath('data.capabilities.brand_template', true)
            ->assertJsonPath('data.capabilities.autofill', false);

        $this->actingAs($user)->getJson('/api/gestion-pedagogica/canva/plantillas?school_id='.$school->id)
            ->assertForbidden()
            ->assertJsonPath('code', 'CANVA_AUTOFILL_CAPABILITY_REQUIRED');
        Http::assertNothingSent();
    }

    public function test_development_trial_flag_only_bypasses_autofill_in_explicitly_safe_environments(): void
    {
        [$user, $school] = $this->userAndSchool();
        $this->connection($user, $school, ['brand_template', 'resize']);
        Http::fake();

        foreach (['production', 'prod', 'staging'] as $environment) {
            config()->set([
                'canva.autofill_trial_enabled' => true,
                'app.env' => $environment,
            ]);

            $this->actingAs($user)->getJson('/api/gestion-pedagogica/canva/conexion?school_id='.$school->id)
                ->assertOk()
                ->assertJsonPath('data.enterprise_autofill', false)
                ->assertJsonPath('data.trial_enabled', false)
                ->assertJsonPath('data.mode', 'unavailable')
                ->assertJsonPath('data.autofill_available', false);
            $this->actingAs($user)->getJson('/api/gestion-pedagogica/canva/plantillas?school_id='.$school->id)
                ->assertForbidden()
                ->assertJsonPath('code', 'CANVA_AUTOFILL_CAPABILITY_REQUIRED');
        }
        Http::assertNothingSent();
    }

    public function test_development_trial_allows_brand_template_catalog_validation_and_sync_without_enterprise_capability(): void
    {
        config()->set('canva.autofill_trial_enabled', true);
        Queue::fake();
        [$user, $school] = $this->userAndSchool();
        $connection = $this->connection($user, $school, ['brand_template', 'resize', 'export_png_transparency']);
        $fields = array_fill_keys(app(CanvaTemplateFieldMapper::class)->expectedFields(12), ['type' => 'text']);
        Http::fake([
            'https://api.canva.test/rest/v1/brand-templates/manual_trial_template/dataset' => Http::response(['dataset' => $fields]),
            'https://api.canva.test/rest/v1/brand-templates*' => Http::response(['items' => []]),
        ]);

        $this->actingAs($user)->getJson('/api/gestion-pedagogica/canva/conexion?school_id='.$school->id)
            ->assertOk()
            ->assertJsonPath('data.enterprise_autofill', false)
            ->assertJsonPath('data.trial_enabled', true)
            ->assertJsonPath('data.mode', 'development_trial')
            ->assertJsonPath('data.autofill_available', true);
        $this->actingAs($user)->getJson('/api/gestion-pedagogica/canva/plantillas?school_id='.$school->id)
            ->assertOk()
            ->assertJsonPath('data.items', [])
            ->assertJsonPath('data.continuation', null);
        $this->actingAs($user)->postJson('/api/gestion-pedagogica/canva/plantillas/manual_trial_template/validar', [
            'school_id' => $school->id,
            'slide_count' => 12,
        ])->assertOk()
            ->assertJsonPath('data.compatible', true);

        $presentation = $this->presentation($user, $school, $connection);
        $this->actingAs($user)
            ->postJson("/api/gestion-pedagogica/presentaciones/{$presentation->uuid}/canva/sincronizar")
            ->assertAccepted()
            ->assertJsonPath('data.status', 'pending');
        Queue::assertPushed(PublishClassPresentationToCanvaJob::class);
    }

    public function test_a_second_teacher_in_the_same_school_cannot_see_the_first_teachers_canva_connection(): void
    {
        [$owner, $school] = $this->userAndSchool();
        $this->connection($owner, $school);
        $teacher = User::factory()->create(['active' => true]);
        $role = Role::query()->create(['slug' => 'canva_teacher_test', 'name' => 'Docente Canva Test', 'active' => true]);
        $permissionIds = collect(['class-presentations.view', 'class-presentations.create'])
            ->map(fn (string $slug): int => Permission::query()->firstOrCreate(
                ['slug' => $slug],
                ['name' => $slug, 'active' => true],
            )->id);
        $role->permissions()->sync($permissionIds);
        $teacher->roles()->sync([$role->id]);
        $school->users()->attach($teacher->id, ['active' => true, 'role_snapshot' => 'Docente']);

        $response = $this->actingAs($teacher)->getJson('/api/gestion-pedagogica/canva/conexion?school_id='.$school->id);

        $response->assertOk()
            ->assertJsonPath('data.configured', true)
            ->assertJsonPath('data.connected', false)
            ->assertJsonPath('data.connection_id', null)
            ->assertJsonPath('data.canva_user_id', null);
        $this->assertStringNotContainsString('canva-user-test', $response->getContent());
    }

    public function test_expired_access_token_is_refreshed_once_and_both_rotated_tokens_remain_encrypted(): void
    {
        [$user, $school] = $this->userAndSchool();
        $connection = $this->connection($user, $school);
        $connection->forceFill(['access_token_expires_at' => now()->subMinute()])->save();
        Http::fake([
            'https://api.canva.test/rest/v1/oauth/token' => Http::response([
                'access_token' => 'rotated-access-token',
                'refresh_token' => 'rotated-refresh-token',
                'expires_in' => 3600,
                'scope' => implode(' ', (array) config('canva.scopes')),
            ]),
        ]);

        $this->assertSame('rotated-access-token', app(CanvaTokenService::class)->accessToken($connection));

        $connection->refresh();
        $this->assertSame('rotated-access-token', $connection->access_token_encrypted);
        $this->assertSame('rotated-refresh-token', $connection->refresh_token_encrypted);
        $this->assertNotSame('rotated-access-token', DB::table('canva_connections')->where('id', $connection->id)->value('access_token_encrypted'));
        $this->assertNotSame('rotated-refresh-token', DB::table('canva_connections')->where('id', $connection->id)->value('refresh_token_encrypted'));
        Http::assertSentCount(1);
    }

    public function test_publish_job_sends_the_strict_dataset_and_persists_the_finished_canva_design(): void
    {
        [$user, $school] = $this->userAndSchool();
        $connection = $this->connection($user, $school);
        $presentation = $this->presentation($user, $school, $connection);
        $fields = array_fill_keys(app(CanvaTemplateFieldMapper::class)->expectedFields(12), ['type' => 'text']);
        Http::fake([
            'https://api.canva.test/rest/v1/brand-templates/template_job_12/dataset' => Http::response(['dataset' => $fields]),
            'https://api.canva.test/rest/v1/autofills' => Http::response(['job' => [
                'id' => 'autofill-job-1',
                'status' => 'success',
                'result' => ['type' => 'create_design', 'design' => [
                    'id' => 'design-1',
                    'url' => 'https://www.canva.com/design/design-1/edit',
                    'urls' => [
                        'edit_url' => 'https://www.canva.com/api/design/edit-token/edit',
                        'view_url' => 'https://www.canva.com/api/design/view-token/view',
                    ],
                    'thumbnail' => ['url' => 'https://document-export.canva.com/design-1-thumbnail.png'],
                ]],
            ]]),
        ]);

        app()->call([new PublishClassPresentationToCanvaJob($presentation->id), 'handle']);

        $presentation->refresh();
        $this->assertSame(CanvaPublicationStatus::Success, $presentation->canva_status);
        $this->assertSame('autofill-job-1', $presentation->canva_autofill_job_id);
        $this->assertSame('design-1', $presentation->canva_design_id);
        $this->assertSame('https://www.canva.com/api/design/edit-token/edit', $presentation->canva_edit_url);
        $this->assertNotNull($presentation->canva_completed_at);
        Http::assertSent(fn (HttpRequest $request): bool => $request->method() === 'POST'
            && $request->url() === 'https://api.canva.test/rest/v1/autofills'
            && $request['type'] === 'create_from_brand_template'
            && $request['brand_template_id'] === 'template_job_12'
            && data_get($request->data(), 'data.TITLE.type') === 'text'
            && data_get($request->data(), 'data.S12_BULLETS.type') === 'text');
    }

    public function test_development_trial_job_persists_safe_trial_information_without_a_migration(): void
    {
        config()->set('canva.autofill_trial_enabled', true);
        [$user, $school] = $this->userAndSchool();
        $connection = $this->connection($user, $school, ['brand_template', 'resize']);
        $presentation = $this->presentation($user, $school, $connection);
        $fields = array_fill_keys(app(CanvaTemplateFieldMapper::class)->expectedFields(12), ['type' => 'text']);
        Http::fake([
            'https://api.canva.test/rest/v1/brand-templates/template_job_12/dataset' => Http::response(['dataset' => $fields]),
            'https://api.canva.test/rest/v1/autofills' => Http::response(['job' => [
                'id' => 'autofill-trial-job-1',
                'status' => 'success',
                'result' => [
                    'type' => 'create_design',
                    'design' => [
                        'id' => 'trial-design-1',
                        'urls' => ['edit_url' => 'https://www.canva.com/design/trial-design-1/edit'],
                    ],
                    'trial_information' => [
                        'uses_remaining' => 1,
                        'upgrade_url' => 'https://www.canva.com/enterprise/',
                    ],
                ],
            ]]),
        ]);

        app()->call([new PublishClassPresentationToCanvaJob($presentation->id), 'handle']);

        $presentation->refresh();
        $this->assertSame(CanvaPublicationStatus::Success, $presentation->canva_status);
        $this->assertSame('trial-design-1', $presentation->canva_design_id);
        $this->assertSame('development_trial', data_get($presentation->configuration, 'canva_trial.mode'));
        $this->assertSame('active', data_get($presentation->configuration, 'canva_trial.status'));
        $this->assertSame(1, data_get($presentation->configuration, 'canva_trial.uses_remaining'));
        $this->assertSame('https://www.canva.com/enterprise/', data_get($presentation->configuration, 'canva_trial.upgrade_url'));
        $this->assertNotEmpty(data_get($presentation->configuration, 'canva_trial.observed_at'));
    }

    public function test_development_trial_http_quota_is_terminal_and_does_not_retry(): void
    {
        config()->set('canva.autofill_trial_enabled', true);
        [$user, $school] = $this->userAndSchool();
        $connection = $this->connection($user, $school, ['brand_template', 'resize']);
        $presentation = $this->presentation($user, $school, $connection);
        $fields = array_fill_keys(app(CanvaTemplateFieldMapper::class)->expectedFields(12), ['type' => 'text']);
        Http::fake([
            'https://api.canva.test/rest/v1/brand-templates/template_job_12/dataset' => Http::response(['dataset' => $fields]),
            'https://api.canva.test/rest/v1/autofills' => Http::response([
                'code' => 'quota_exceeded',
                'message' => 'Free autofill quota has been exceeded.',
                'upsell_url' => 'https://www.canva.com/enterprise/',
            ], 429),
        ]);

        app()->call([new PublishClassPresentationToCanvaJob($presentation->id), 'handle']);

        $presentation->refresh();
        $this->assertSame(CanvaPublicationStatus::Failed, $presentation->canva_status);
        $this->assertSame('CANVA_TRIAL_QUOTA_EXCEEDED', $presentation->canva_failure_code);
        $this->assertSame('exhausted', data_get($presentation->configuration, 'canva_trial.status'));
        $this->assertSame(0, data_get($presentation->configuration, 'canva_trial.uses_remaining'));
        Http::assertSentCount(2);
    }

    public function test_remote_trial_quota_failure_is_terminal_when_polling_the_job(): void
    {
        config()->set('canva.autofill_trial_enabled', true);
        [$user, $school] = $this->userAndSchool();
        $connection = $this->connection($user, $school, ['brand_template', 'resize']);
        $presentation = $this->presentation($user, $school, $connection);
        $presentation->forceFill([
            'canva_autofill_job_id' => 'autofill-trial-job-exhausted',
            'canva_status' => CanvaPublicationStatus::InProgress,
        ])->save();
        Http::fake([
            'https://api.canva.test/rest/v1/autofills/autofill-trial-job-exhausted' => Http::response(['job' => [
                'id' => 'autofill-trial-job-exhausted',
                'status' => 'failed',
                'error' => [
                    'code' => 'trial_quota_exceeded',
                    'message' => 'The Autofill trial quota was exhausted.',
                ],
            ]]),
        ]);

        app()->call([new PublishClassPresentationToCanvaJob($presentation->id), 'handle']);

        $presentation->refresh();
        $this->assertSame(CanvaPublicationStatus::Failed, $presentation->canva_status);
        $this->assertSame('CANVA_TRIAL_QUOTA_EXCEEDED', $presentation->canva_failure_code);
        $this->assertSame('exhausted', data_get($presentation->configuration, 'canva_trial.status'));
        Http::assertSentCount(1);

        Queue::fake();
        $this->actingAs($user)
            ->postJson("/api/gestion-pedagogica/presentaciones/{$presentation->uuid}/canva/sincronizar")
            ->assertAccepted();
        $presentation->refresh();
        $this->assertSame(CanvaPublicationStatus::Pending, $presentation->canva_status);
        $this->assertNull($presentation->canva_autofill_job_id);
        Queue::assertPushed(PublishClassPresentationToCanvaJob::class);
    }

    public function test_edit_link_is_refreshed_for_the_owner_and_canva_state_is_available_in_the_status_endpoint(): void
    {
        [$user, $school] = $this->userAndSchool();
        $connection = $this->connection($user, $school);
        $presentation = $this->presentation($user, $school, $connection);
        $presentation->forceFill([
            'canva_status' => CanvaPublicationStatus::Success,
            'canva_autofill_job_id' => 'autofill-job-links',
            'canva_design_id' => 'design-links',
            'canva_completed_at' => now(),
        ])->save();
        Http::fake([
            'https://api.canva.test/rest/v1/designs/design-links' => Http::response(['design' => [
                'id' => 'design-links',
                'owner' => ['user_id' => 'canva-user-test', 'team_id' => 'canva-team-test'],
                'url' => 'https://www.canva.com/design/design-links/edit',
                'urls' => [
                    'edit_url' => 'https://www.canva.com/api/design/refreshed-edit/edit',
                    'view_url' => 'https://www.canva.com/api/design/refreshed-view/view',
                ],
            ]]),
        ]);

        $this->actingAs($user)
            ->postJson("/api/gestion-pedagogica/presentaciones/{$presentation->uuid}/canva/enlace-edicion")
            ->assertOk()
            ->assertJsonPath('data.design_id', 'design-links')
            ->assertJsonPath('data.edit_url', 'https://www.canva.com/api/design/refreshed-edit/edit');
        $this->actingAs($user)
            ->getJson("/api/gestion-pedagogica/presentaciones/{$presentation->uuid}/estado")
            ->assertOk()
            ->assertJsonPath('data.presentation_provider', 'canva')
            ->assertJsonPath('data.canva.status', 'success')
            ->assertJsonPath('data.canva.design_id', 'design-links')
            ->assertJsonPath('data.canva.edit_url', 'https://www.canva.com/api/design/refreshed-edit/edit');
    }

    /** @return array{User,School} */
    private function userAndSchool(): array
    {
        $user = User::factory()->create(['active' => true]);
        $role = Role::query()->firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin', 'active' => true]);
        $user->roles()->sync([$role->id]);
        $school = School::query()->create([
            'rbd' => '88991-0',
            'name' => 'Escuela Canva Test',
            'timezone' => 'America/Santiago',
            'active' => true,
        ]);
        $school->users()->attach($user->id, ['active' => true, 'role_snapshot' => 'Docente']);

        return [$user, $school];
    }

    /** @param list<string> $capabilities */
    private function connection(
        User $user,
        School $school,
        array $capabilities = ['brand_template', 'autofill'],
    ): CanvaConnection {
        return CanvaConnection::query()->create([
            'school_id' => $school->id,
            'user_id' => $user->id,
            'canva_user_id' => 'canva-user-test',
            'canva_team_id' => 'canva-team-test',
            'display_name' => 'Docente Canva',
            'access_token_encrypted' => 'access-token-test',
            'refresh_token_encrypted' => 'refresh-token-test',
            'scopes' => config('canva.scopes'),
            'capabilities' => $capabilities,
            'status' => CanvaConnectionStatus::Active,
            'access_token_expires_at' => now()->addHour(),
        ]);
    }

    private function presentation(User $user, School $school, CanvaConnection $connection): ClassPresentation
    {
        $year = AcademicYear::factory()->create([
            'year' => 2041,
            'name' => '2041',
            'starts_at' => '2041-03-01',
            'ends_at' => '2041-12-20',
            'is_active' => true,
            'is_closed' => false,
        ]);
        $level = EducationLevel::factory()->create(['name' => '7° Básico Canva', 'order' => 741, 'type' => 'basica']);
        $course = CourseSection::factory()->create([
            'academic_year_id' => $year->id,
            'education_level_id' => $level->id,
            'display_name' => '7° Básico Canva',
            'section_name' => 'C',
            'active' => true,
        ]);
        $subject = ScheduleSubject::query()->create([
            'name' => 'Orientación Canva',
            'code' => 'ORI-CANVA',
            'area' => 'Orientación',
            'color' => '#1A8D86',
            'active' => true,
        ]);
        $catalog = CurriculumCatalog::query()->create([
            'code' => 'CANVA-CATALOG-2041',
            'name' => 'Catálogo Canva 2041',
            'version' => '2041',
            'authority' => 'Ministerio de Educación de Chile',
            'active' => true,
        ]);
        $version = CurriculumVersion::query()->create([
            'name' => 'Versión Canva 2041',
            'issuing_authority' => 'Ministerio de Educación de Chile',
            'publication_year' => 2041,
            'status' => 'published',
            'identity_hash' => hash('sha256', 'canva-version-2041'),
        ]);
        $program = CurriculumProgram::query()->create([
            'schedule_subject_id' => $subject->id,
            'education_level_id' => $level->id,
            'curriculum_version_id' => $version->id,
            'curriculum_catalog_id' => $catalog->id,
            'level_code' => 'BASICA',
            'grade_code' => '7B',
            'official_name' => 'Orientación Canva 7° básico',
            'official_code' => 'OR07-CANVA',
            'status' => 'published',
            'identity_hash' => hash('sha256', 'canva-program-2041'),
            'published_at' => now(),
            'created_by' => $user->id,
        ]);
        $unit = CurriculumUnit::query()->create([
            'curriculum_program_id' => $program->id,
            'unit_code' => 'CANVA-U1',
            'official_title' => 'Convivencia y acuerdos',
            'official_order' => 1,
        ]);
        $deck = json_decode((string) file_get_contents(base_path('tests/Fixtures/ClassPresentations/orientation-7b-conflict-resolution.json')), true, 512, JSON_THROW_ON_ERROR);

        return ClassPresentation::query()->create([
            'school_id' => $school->id,
            'user_id' => $user->id,
            'academic_year_id' => $year->id,
            'course_id' => $course->id,
            'subject_id' => $subject->id,
            'unit_id' => $unit->id,
            'title' => 'Clase Canva de prueba',
            'status' => ClassPresentationStatus::Ready,
            'progress' => 100,
            'presentation_provider' => 'canva',
            'canva_connection_id' => $connection->id,
            'canva_brand_template_id' => 'template_job_12',
            'canva_brand_template_title' => 'Plantilla institucional',
            'canva_status' => CanvaPublicationStatus::Pending,
            'configuration' => ['presentation_provider' => 'canva', 'slide_count' => 12],
            'curricular_snapshot' => [
                'course' => ['name' => $course->display_name],
                'subject' => ['name' => $subject->name],
                'unit' => ['title' => $unit->official_title],
                'objectives' => [['code' => 'OR07 OA 06', 'description' => 'Construir acuerdos respetuosos.']],
            ],
            'deck_json' => $deck,
            'model' => 'gpt-5.6',
            'prompt_name' => 'class-presentation',
            'prompt_version' => 'v1.0.0',
            'generated_at' => now(),
        ]);
    }
}
