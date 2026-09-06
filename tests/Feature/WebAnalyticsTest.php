<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\SystemModule;
use App\Models\User;
use App\Models\WebAnalyticsPageView;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WebAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-06 16:00:00');
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_public_tracker_records_anonymous_page_views_and_idempotent_engagement(): void
    {
        $visitorId = (string) Str::uuid();
        $sessionId = (string) Str::uuid();

        $response = $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (iPhone) AppleWebKit/537.36 Chrome/126.0 Safari/537.36',
            'Sec-Fetch-Site' => 'same-origin',
        ])->postJson('/api/public/web-analytics/page-view', [
            'visitor_id' => $visitorId,
            'session_id' => $sessionId,
            'path' => '/noticias/27?correo=privado@ejemplo.cl',
            'title' => '<b>Noticia futura</b> | CNSC',
            'page_type' => 'detail',
            'content_type' => 'news',
            'content_identifier' => '27',
            'content_slug' => 'noticia-futura',
            'referrer_host' => 'www.google.cl',
            'viewport_width' => 390,
        ])->assertCreated()->assertJsonStructure(['visit_id']);

        $visitId = $response->json('visit_id');
        $view = WebAnalyticsPageView::query()->where('public_id', $visitId)->firstOrFail();

        $this->assertSame('/noticias/27', $view->path);
        $this->assertSame('Noticia futura | CNSC', $view->title);
        $this->assertSame('news', $view->content_type);
        $this->assertSame('google.cl', $view->traffic_source);
        $this->assertSame('search', $view->traffic_channel);
        $this->assertSame('mobile', $view->device_type);
        $this->assertSame('Chrome', $view->browser_family);
        $this->assertNotSame($visitorId, $view->visitor_hash);
        $this->assertNotSame($sessionId, $view->session_hash);
        $this->assertStringNotContainsString('correo=', $view->path);

        $payload = [
            'visit_id' => $visitId,
            'visitor_id' => $visitorId,
            'session_id' => $sessionId,
            'engaged_seconds' => 42,
            'max_scroll_depth' => 76,
            'interaction_count' => 3,
        ];

        $this->postJson('/api/public/web-analytics/engagement', $payload)->assertNoContent();
        $this->postJson('/api/public/web-analytics/engagement', array_merge($payload, [
            'engaged_seconds' => 12,
            'max_scroll_depth' => 20,
            'interaction_count' => 1,
        ]))->assertNoContent();

        $view->refresh();
        $this->assertSame(42, $view->engaged_seconds);
        $this->assertSame(76, $view->max_scroll_depth);
        $this->assertSame(3, $view->interaction_count);
        $this->assertTrue($view->is_engaged);
    }

    public function test_tracker_ignores_bots_and_cross_site_submissions_and_accepts_future_content_types(): void
    {
        $payload = $this->trackingPayload();

        $this->withHeader('User-Agent', 'Googlebot/2.1')
            ->postJson('/api/public/web-analytics/page-view', $payload)
            ->assertNoContent();

        $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0 Chrome/126.0 Safari/537.36',
            'Sec-Fetch-Site' => 'cross-site',
            'Origin' => 'https://spam.example',
        ])->postJson('/api/public/web-analytics/page-view', $payload)->assertNoContent();

        $this->assertDatabaseCount('web_analytics_page_views', 0);

        $this->flushHeaders()
            ->withHeader('User-Agent', 'Mozilla/5.0 Chrome/126.0 Safari/537.36')
            ->postJson('/api/public/web-analytics/page-view', array_merge($payload, [
                'content_type' => 'podcast',
                'content_identifier' => 'episode-2027-01',
                'content_slug' => 'voces-del-colegio',
            ]))
            ->assertCreated();

        $this->assertDatabaseHas('web_analytics_page_views', [
            'content_type' => 'podcast',
            'content_identifier' => 'episode-2027-01',
        ]);
    }

    public function test_authorized_dashboard_returns_shopify_style_summary_and_content_performance(): void
    {
        $this->pageView([
            'public_id' => (string) Str::uuid(),
            'visitor_hash' => str_repeat('a', 64),
            'session_hash' => str_repeat('1', 64),
            'path' => '/noticias/1',
            'title' => 'Noticia destacada | Noticias',
            'content_type' => 'news',
            'content_identifier' => '1',
            'content_slug' => 'noticia-destacada',
            'traffic_source' => 'instagram.com',
            'traffic_channel' => 'social',
            'device_type' => 'mobile',
            'engaged_seconds' => 65,
            'max_scroll_depth' => 90,
            'interaction_count' => 2,
            'is_engaged' => true,
        ]);
        $this->pageView([
            'public_id' => (string) Str::uuid(),
            'visitor_hash' => str_repeat('a', 64),
            'session_hash' => str_repeat('1', 64),
            'path' => '/',
            'title' => 'Colegio CNSC',
            'engaged_seconds' => 20,
            'max_scroll_depth' => 55,
            'is_engaged' => true,
        ]);
        $this->pageView([
            'public_id' => (string) Str::uuid(),
            'visitor_hash' => str_repeat('b', 64),
            'session_hash' => str_repeat('2', 64),
            'path' => '/eventos/8',
            'title' => 'Encuentro familiar | Eventos',
            'content_type' => 'event',
            'content_identifier' => '8',
            'content_slug' => 'encuentro-familiar',
            'engaged_seconds' => 3,
            'is_engaged' => false,
        ]);

        Sanctum::actingAs($this->authorizedUser());

        $this->getJson('/api/admin/web-analytics?from=2026-09-01&to=2026-09-06')
            ->assertOk()
            ->assertJsonPath('summary.views.value', 3)
            ->assertJsonPath('summary.visitors.value', 2)
            ->assertJsonPath('summary.sessions.value', 2)
            ->assertJsonPath('summary.engagement_rate.value', 66.7)
            ->assertJsonPath('summary.bounce_rate.value', 50)
            ->assertJsonPath('summary.pages_per_session.value', 1.5)
            ->assertJsonPath('sources.0.channel', 'direct')
            ->assertJsonCount(6, 'daily')
            ->assertJsonFragment([
                'content_type' => 'news',
                'title' => 'Noticia destacada | Noticias',
            ])
            ->assertJsonFragment(['content_type' => 'event']);

        $this->getJson('/api/admin/web-analytics?from=2026-09-01&to=2026-09-06&content_type=news')
            ->assertOk()
            ->assertJsonPath('summary.views.value', 1)
            ->assertJsonCount(1, 'content_performance');
    }

    public function test_dashboard_requires_its_permission_and_validates_the_date_range(): void
    {
        $this->getJson('/api/admin/web-analytics')->assertUnauthorized();

        $user = User::factory()->create(['active' => true]);
        Sanctum::actingAs($user);
        $this->getJson('/api/admin/web-analytics')->assertForbidden();

        Sanctum::actingAs($this->authorizedUser());
        $this->getJson('/api/admin/web-analytics?from=2025-01-01&to=2026-09-06')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['to']);
    }

    public function test_web_analytics_permission_and_module_are_registered_for_navigation(): void
    {
        $permission = Permission::query()->where('slug', 'ver_metricas_sitio')->first();
        $module = SystemModule::query()->where('slug', 'public_site_analytics')->first();
        $parent = SystemModule::query()->where('slug', 'public_site')->first();

        $this->assertNotNull($permission);
        $this->assertNotNull($module);
        $this->assertNotNull($parent);
        $this->assertSame('/admin/metricas-web', $module->frontend_route);
        $this->assertSame($parent->id, $module->parent_id);
    }

    private function authorizedUser(): User
    {
        $user = User::factory()->create(['active' => true]);
        $role = Role::query()->firstOrCreate(['slug' => 'analytics_manager'], [
            'name' => 'Analítica web',
            'active' => true,
        ]);
        $role->permissions()->syncWithoutDetaching([
            Permission::query()->where('slug', 'ver_metricas_sitio')->value('id'),
        ]);
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
    }

    private function trackingPayload(): array
    {
        return [
            'visitor_id' => (string) Str::uuid(),
            'session_id' => (string) Str::uuid(),
            'path' => '/contenido-futuro',
            'title' => 'Contenido futuro',
            'page_type' => 'detail',
            'viewport_width' => 1440,
        ];
    }

    private function pageView(array $overrides): WebAnalyticsPageView
    {
        return WebAnalyticsPageView::query()->create(array_merge([
            'public_id' => (string) Str::uuid(),
            'visitor_hash' => str_repeat('c', 64),
            'session_hash' => str_repeat('3', 64),
            'viewed_on' => '2026-09-06',
            'page_type' => 'page',
            'content_type' => null,
            'content_identifier' => null,
            'content_slug' => null,
            'path' => '/',
            'title' => 'Colegio CNSC',
            'referrer_host' => null,
            'traffic_source' => 'Directo',
            'traffic_channel' => 'direct',
            'campaign' => null,
            'device_type' => 'desktop',
            'browser_family' => 'Chrome',
            'viewport_width' => 1440,
            'started_at' => now(),
            'last_seen_at' => now(),
            'engaged_seconds' => 0,
            'max_scroll_depth' => 0,
            'interaction_count' => 0,
            'is_engaged' => false,
        ], $overrides));
    }
}
