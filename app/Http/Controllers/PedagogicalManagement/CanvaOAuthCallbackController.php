<?php

namespace App\Http\Controllers\PedagogicalManagement;

use App\Exceptions\PedagogicalManagement\CanvaIntegrationException;
use App\Http\Controllers\Controller;
use App\Services\PedagogicalManagement\ClassPresentations\Canva\CanvaOAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class CanvaOAuthCallbackController extends Controller
{
    public function __invoke(Request $request, CanvaOAuthService $oauth): RedirectResponse
    {
        $state = mb_substr((string) $request->query('state'), 0, 500);
        if (trim((string) $request->query('error')) !== '') {
            return $this->redirectWith($oauth->cancel($state), [
                'canva' => 'error',
                'code' => 'CANVA_OAUTH_DENIED',
            ]);
        }
        $redirectTo = $oauth->redirectForState($state);

        try {
            $result = $oauth->complete(
                $state,
                mb_substr((string) $request->query('code'), 0, 4000),
            );

            return $this->redirectWith($result['redirect_to'], [
                'canva' => 'connected',
                'connection' => $result['connection']->uuid,
            ]);
        } catch (CanvaIntegrationException $exception) {
            Log::warning('Canva OAuth callback failed.', [
                'provider' => 'canva',
                'operation' => 'oauth_callback',
                'failure_code' => $exception->failureCode,
                'http_status' => $exception->httpStatus,
                'retryable' => $exception->retryable,
            ]);

            return $this->redirectWith($redirectTo, [
                'canva' => 'error',
                'code' => $exception->failureCode,
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return $this->redirectWith($redirectTo, [
                'canva' => 'error',
                'code' => 'CANVA_OAUTH_CALLBACK_FAILED',
            ]);
        }
    }

    /** @param array<string,string> $query */
    private function redirectWith(string $path, array $query): RedirectResponse
    {
        $separator = str_contains($path, '?') ? '&' : '?';

        return redirect()->to(url($path.$separator.http_build_query($query, '', '&', PHP_QUERY_RFC3986)));
    }
}
