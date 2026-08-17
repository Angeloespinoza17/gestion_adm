<?php

namespace App\Enums\LibroDigital;

enum SessionStatus: string
{
    case Scheduled = 'scheduled';
    case Draft = 'draft';
    case AttendanceInProgress = 'attendance_in_progress';
    case ReadyToSign = 'ready_to_sign';
    case Signing = 'signing';
    case Signed = 'signed';
    case Amended = 'amended';
    case Cancelled = 'cancelled';
    case Closed = 'closed';
}
