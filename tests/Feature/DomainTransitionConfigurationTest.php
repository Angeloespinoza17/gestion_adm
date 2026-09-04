<?php

namespace Tests\Feature;

use App\Http\Middleware\TrustHosts;
use Tests\TestCase;

class DomainTransitionConfigurationTest extends TestCase
{
    public function test_trusted_host_patterns_are_exact_and_explicit(): void
    {
        config()->set('app.url', 'https://cnscvaldivia.cl');
        config()->set('app.trusted_hosts', [
            'www.cnscvaldivia.cl',
            'cnscgestion.cl',
            'www.cnscgestion.cl',
            ' cnscgestion.cl ',
        ]);

        $patterns = (new TrustHosts($this->app))->hosts();

        $this->assertSame([
            '^cnscvaldivia\\.cl\\z',
            '^www\\.cnscvaldivia\\.cl\\z',
            '^cnscgestion\\.cl\\z',
            '^www\\.cnscgestion\\.cl\\z',
        ], $patterns);

        foreach ($patterns as $pattern) {
            $this->assertDoesNotMatchRegularExpression('/\.\+|\.\*/', $pattern);
        }

        $this->assertSame(1, preg_match('{'.$patterns[0].'}i', 'cnscvaldivia.cl'));
        $this->assertSame(0, preg_match('{'.$patterns[0].'}i', 'otro.cnscvaldivia.cl'));
        $this->assertSame(0, preg_match('{'.$patterns[0].'}i', 'cnscvaldivia.cl.example'));
    }

    public function test_htaccess_redirects_transition_hosts_with_308_and_strict_https(): void
    {
        $contents = file_get_contents(public_path('.htaccess'));

        $this->assertIsString($contents);
        $this->assertStringContainsString(
            'RewriteCond %{HTTP_HOST} ^(?:www\\.)?cnscgestion\\.cl$ [NC,OR]',
            $contents
        );
        $this->assertStringContainsString(
            'RewriteCond %{HTTP_HOST} ^www\\.cnscvaldivia\\.cl$ [NC]',
            $contents
        );
        $this->assertStringContainsString(
            'RewriteRule ^ https://cnscvaldivia.cl%{REQUEST_URI} [R=308,L]',
            $contents
        );
        $this->assertStringNotContainsString('HTTP:X-Forwarded-Proto', $contents);
        $this->assertSame(2, substr_count(
            $contents,
            'RewriteRule ^ https://cnscvaldivia.cl%{REQUEST_URI} [R=308,L]'
        ));
        $this->assertStringNotContainsString('https://cnscgestion.cl%{REQUEST_URI}', $contents);
    }
}
