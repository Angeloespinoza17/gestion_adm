<?php

namespace App\Enums\LibroDigital;

enum AbsenceCaseStatus: string
{
    case Open = 'open';
    case Monitoring = 'monitoring';
    case Contacted = 'contacted';
    case Escalated = 'escalated';
    case Resolved = 'resolved';
    case Closed = 'closed';
}
