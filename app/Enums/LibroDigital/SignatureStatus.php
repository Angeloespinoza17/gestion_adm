<?php

namespace App\Enums\LibroDigital;

enum SignatureStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Rejected = 'rejected';
    case Failed = 'failed';
    case Expired = 'expired';
}
