<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class DeployController extends Controller
{
    public function status(): JsonResponse
    {
        return response()->json([
            'enabled' => $this->isEnabled(),
            'configured' => $this->isConfigured(),
            'target' => env('DEPLOY_HOST'),
            'path' => env('DEPLOY_REMOTE_PATH'),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, private');
    }

    public function run(): JsonResponse
    {
        if (!$this->isEnabled()) {
            return response()->json([
                'success' => false,
                'message' => 'Deploy deshabilitado. Activa DEPLOY_ENABLED=true en .env.',
            ], 403);
        }

        if (!$this->isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'Faltan variables DEPLOY_HOST, DEPLOY_USER o DEPLOY_REMOTE_PATH en .env.',
            ], 422);
        }

        $script = base_path('scripts/deploy.sh');

        if (!file_exists($script)) {
            return response()->json([
                'success' => false,
                'message' => 'No existe scripts/deploy.sh.',
            ], 500);
        }

        set_time_limit((int) env('DEPLOY_TIMEOUT', 900));

        [$exitCode, $output] = $this->execute($script);

        return response()->json([
            'success' => $exitCode === 0,
            'message' => $exitCode === 0 ? 'Deploy terminado correctamente.' : 'Deploy falló.',
            'exitCode' => $exitCode,
            'output' => $output,
        ], $exitCode === 0 ? 200 : 500);
    }

    private function isEnabled(): bool
    {
        return filter_var(env('DEPLOY_ENABLED', false), FILTER_VALIDATE_BOOLEAN);
    }

    private function isConfigured(): bool
    {
        return filled(env('DEPLOY_HOST')) && filled(env('DEPLOY_USER')) && filled(env('DEPLOY_REMOTE_PATH'));
    }

    private function execute(string $script): array
    {
        $descriptors = [
            1 => ['pipe', 'w'],
        ];

        $environment = array_merge($_ENV, [
            'DEPLOY_HOST' => env('DEPLOY_HOST'),
            'DEPLOY_PORT' => env('DEPLOY_PORT', 22),
            'DEPLOY_USER' => env('DEPLOY_USER'),
            'DEPLOY_REMOTE_PATH' => env('DEPLOY_REMOTE_PATH'),
            'DEPLOY_PHP_BIN' => env('DEPLOY_PHP_BIN', 'php'),
            'DEPLOY_COMPOSER_BIN' => env('DEPLOY_COMPOSER_BIN', 'composer'),
            'DEPLOY_TIMEOUT' => env('DEPLOY_TIMEOUT', 900),
            'DEPLOY_REMOTE_OWNER' => env('DEPLOY_REMOTE_OWNER', ''),
            'DEPLOY_SSH_KEY' => env('DEPLOY_SSH_KEY', ''),
            'HOME' => env('DEPLOY_HOME', getenv('HOME') ?: ''),
            'PATH' => getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin',
        ]);

        $process = proc_open('/bin/bash ' . escapeshellarg($script) . ' 2>&1', $descriptors, $pipes, base_path(), $environment);

        if (!is_resource($process)) {
            return [1, 'No se pudo iniciar el proceso de deploy.'];
        }

        $output = stream_get_contents($pipes[1]);

        fclose($pipes[1]);

        return [proc_close($process), $output];
    }
}
