<?php

namespace App\Enums\PedagogicalManagement;

enum CanvaPublicationStatus: string
{
    case Pending = 'pending';
    case Submitting = 'submitting';
    case InProgress = 'in_progress';
    case Success = 'success';
    case Failed = 'failed';
}
