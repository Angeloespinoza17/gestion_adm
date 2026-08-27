<?php

namespace App\Enums\PedagogicalManagement;

enum InstrumentWorkflowStatus: string
{
    case Submitted = 'submitted';
    case RectificationRequested = 'rectification_requested';
    case Resubmitted = 'resubmitted';
    case Approved = 'approved';
    case ApprovedWithObservations = 'approved_with_observations';
    case Archived = 'archived';

    public function isApproved(): bool
    {
        return in_array($this, [self::Approved, self::ApprovedWithObservations], true);
    }
}
