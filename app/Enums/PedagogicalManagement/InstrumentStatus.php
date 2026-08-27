<?php

namespace App\Enums\PedagogicalManagement;

enum InstrumentStatus: string
{
    case Draft = 'draft';
    case Uploaded = 'uploaded';
    case PendingAnalysis = 'pending_analysis';
    case Processing = 'processing';
    case ReviewRequired = 'review_required';
    case ValidatedWithWarnings = 'validated_with_warnings';
    case Validated = 'validated';
    case NotAnalyzable = 'not_analyzable';
    case Failed = 'failed';
    case Archived = 'archived';
}
