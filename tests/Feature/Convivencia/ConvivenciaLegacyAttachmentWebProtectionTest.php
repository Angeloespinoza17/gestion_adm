<?php

namespace Tests\Feature\Convivencia;

use Tests\TestCase;

class ConvivenciaLegacyAttachmentWebProtectionTest extends TestCase
{
    public function test_apache_denies_legacy_public_attachments_before_static_file_bypass(): void
    {
        $configuration = file_get_contents(public_path('.htaccess'));

        $this->assertIsString($configuration);

        $denyRule = 'RewriteRule ^storage/convivencia(?:/|$) - [F,L,NC]';
        $staticFileBypass = 'RewriteCond %{REQUEST_FILENAME} !-f';
        $denyPosition = strpos($configuration, $denyRule);
        $bypassPosition = strpos($configuration, $staticFileBypass);

        $this->assertNotFalse($denyPosition, 'Falta el bloqueo web para adjuntos legacy de Convivencia.');
        $this->assertNotFalse($bypassPosition, 'Falta la regla base de archivos estaticos de Laravel.');
        $this->assertLessThan(
            $bypassPosition,
            $denyPosition,
            'El bloqueo debe evaluarse antes de permitir que Apache sirva archivos existentes.'
        );

        $protectedPath = '~^storage/convivencia(?:/|$)~i';
        $this->assertSame(1, preg_match($protectedPath, 'storage/convivencia/caso/evidencia.pdf'));
        $this->assertSame(1, preg_match($protectedPath, 'storage/CONVIVENCIA/legacy.pdf'));
        $this->assertSame(0, preg_match($protectedPath, 'storage/otro-modulo/archivo.pdf'));
    }
}
