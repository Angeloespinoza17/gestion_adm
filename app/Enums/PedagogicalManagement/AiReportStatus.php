<?php

namespace App\Enums\PedagogicalManagement;

enum AiReportStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    public function terminal(): bool
    {
        return in_array($this, [self::Completed, self::Failed], true);
    }
}
