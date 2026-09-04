<?php

namespace App\Enums\PedagogicalManagement;

enum CanvaConnectionStatus: string
{
    case Active = 'active';
    case ReauthorizationRequired = 'reauthorization_required';
    case Revoked = 'revoked';
}
