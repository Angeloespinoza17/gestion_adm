<?php

namespace App\Enums\LibroDigital;

enum EdeExportStatus: string
{
    case Requested = 'requested';
    case PreflightRunning = 'preflight_running';
    case PreflightFailed = 'preflight_failed';
    case Projecting = 'projecting';
    case Projected = 'projected';
    case Packaging = 'packaging';
    case Generated = 'generated';
    case ValidationQueued = 'validation_queued';
    case Validating = 'validating';
    case Validated = 'validated';
    case ValidationFailed = 'validation_failed';
    case Released = 'released';
    case Stale = 'stale';
    case Revoked = 'revoked';
}
