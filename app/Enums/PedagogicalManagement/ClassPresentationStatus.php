<?php

namespace App\Enums\PedagogicalManagement;

enum ClassPresentationStatus: string
{
    case Draft = 'draft';
    case Queued = 'queued';
    case PreparingContent = 'preparing_content';
    case GeneratingPresentation = 'generating_presentation';
    case Validating = 'validating';
    case Ready = 'ready';
    case Failed = 'failed';
    case Archived = 'archived';

    public function terminal(): bool
    {
        return in_array($this, [self::Ready, self::Failed, self::Archived], true);
    }
}
