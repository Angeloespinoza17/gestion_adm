<?php

namespace App\Enums\PedagogicalManagement;

enum ReviewDecision: string
{
    case Approved = 'approved';
    case ApprovedWithObservations = 'approved_with_observations';
    case RectificationRequested = 'rectification_requested';

    public function workflowStatus(): InstrumentWorkflowStatus
    {
        return match ($this) {
            self::Approved => InstrumentWorkflowStatus::Approved,
            self::ApprovedWithObservations => InstrumentWorkflowStatus::ApprovedWithObservations,
            self::RectificationRequested => InstrumentWorkflowStatus::RectificationRequested,
        };
    }
}
