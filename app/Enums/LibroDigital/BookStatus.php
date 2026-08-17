<?php

namespace App\Enums\LibroDigital;

enum BookStatus: string
{
    case Draft = 'draft';
    case PendingPreflight = 'pending_preflight';
    case Open = 'open';
    case TemporarilyLocked = 'temporarily_locked';
    case Closing = 'closing';
    case Closed = 'closed';
    case Archived = 'archived';
}
