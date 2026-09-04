<?php

namespace App\Services\PedagogicalManagement\ClassPresentations\Canva;

use App\Enums\PedagogicalManagement\CanvaConnectionStatus;
use App\Exceptions\PedagogicalManagement\CanvaIntegrationException;
use App\Models\PedagogicalManagement\CanvaConnection;

class CanvaAutofillAccessPolicy
{
    private const TRIAL_ENVIRONMENTS = ['local', 'testing'];

    public const MODE_ENTERPRISE = 'enterprise';

    public const MODE_DEVELOPMENT_TRIAL = 'development_trial';

    public const MODE_UNAVAILABLE = 'unavailable';

    /**
     * @return array{
     *   enterprise_autofill:bool,
     *   trial_enabled:bool,
     *   mode:string,
     *   autofill_available:bool,
     *   brand_template:bool
     * }
     */
    public function state(?CanvaConnection $connection): array
    {
        $active = $connection?->status === CanvaConnectionStatus::Active;
        $capabilities = array_values(array_filter((array) ($connection?->capabilities ?? []), 'is_string'));
        $brandTemplate = in_array('brand_template', $capabilities, true);
        $enterpriseAutofill = in_array('autofill', $capabilities, true);
        $trialEnabled = $this->trialEnabled();
        $mode = self::MODE_UNAVAILABLE;

        if ($active && $brandTemplate && $enterpriseAutofill) {
            $mode = self::MODE_ENTERPRISE;
        } elseif ($active && $brandTemplate && $trialEnabled) {
            $mode = self::MODE_DEVELOPMENT_TRIAL;
        }

        return [
            'enterprise_autofill' => $enterpriseAutofill,
            'trial_enabled' => $trialEnabled,
            'mode' => $mode,
            'autofill_available' => $mode !== self::MODE_UNAVAILABLE,
            'brand_template' => $brandTemplate,
        ];
    }

    public function allows(?CanvaConnection $connection): bool
    {
        return $this->state($connection)['autofill_available'];
    }

    public function assertAvailable(CanvaConnection $connection): void
    {
        $state = $this->state($connection);
        if ($connection->status !== CanvaConnectionStatus::Active) {
            throw new CanvaIntegrationException(
                'La cuenta de Canva debe volver a conectarse.',
                'CANVA_REAUTHORIZATION_REQUIRED',
                409,
            );
        }
        if (! $state['brand_template']) {
            throw new CanvaIntegrationException(
                'La cuenta conectada no tiene acceso a Brand Templates.',
                'CANVA_BRAND_TEMPLATE_CAPABILITY_REQUIRED',
                403,
            );
        }
        if (! $state['autofill_available']) {
            throw new CanvaIntegrationException(
                'La cuenta conectada no tiene acceso a Autofill y el trial de desarrollo está deshabilitado.',
                'CANVA_AUTOFILL_CAPABILITY_REQUIRED',
                403,
            );
        }
    }

    public function trialEnabled(): bool
    {
        return (bool) config('canva.autofill_trial_enabled', false)
            && in_array(
                strtolower((string) config('app.env', 'production')),
                self::TRIAL_ENVIRONMENTS,
                true,
            );
    }
}
