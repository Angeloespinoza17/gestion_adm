<?php

namespace App\Enums\PedagogicalManagement;

enum AnalysisRunStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case CompletedWithWarnings = 'completed_with_warnings';
    case Failed = 'failed';
    case NotAnalyzable = 'not_analyzable';

    public function terminal(): bool
    {
        return in_array($this, [self::Completed, self::CompletedWithWarnings, self::Failed, self::NotAnalyzable], true);
    }
}
