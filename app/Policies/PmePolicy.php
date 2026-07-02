<?php

namespace App\Policies;

use App\Models\User;
use App\Services\Pme\PmeAccessService;

class PmePolicy
{
    public function __construct(
        private readonly PmeAccessService $accessService,
    ) {
    }

    public function viewModule(User $user): bool
    {
        return $this->accessService->canViewModule($user);
    }

    public function manageConfiguration(User $user): bool
    {
        return $this->accessService->canManageConfiguration($user);
    }

    public function createPlan(User $user): bool
    {
        return $this->accessService->canCreatePlan($user);
    }

    public function editPlan(User $user): bool
    {
        return $this->accessService->canEditPlan($user);
    }

    public function closePlan(User $user): bool
    {
        return $this->accessService->canClosePlan($user);
    }

    public function manageIncomes(User $user): bool
    {
        return $this->accessService->canManageIncomes($user);
    }

    public function viewStudentClassifications(User $user): bool
    {
        return $this->accessService->canViewStudentClassifications($user);
    }

    public function uploadStudentClassifications(User $user): bool
    {
        return $this->accessService->canLoadStudents($user);
    }

    public function manageDimensions(User $user): bool
    {
        return $this->accessService->canManageDimensions($user);
    }

    public function createObjective(User $user): bool
    {
        return $this->accessService->canCreateObjective($user);
    }

    public function editObjective(User $user): bool
    {
        return $this->accessService->canEditObjective($user);
    }

    public function createStrategy(User $user): bool
    {
        return $this->accessService->canCreateStrategy($user);
    }

    public function editStrategy(User $user): bool
    {
        return $this->accessService->canEditStrategy($user);
    }

    public function createIndicator(User $user): bool
    {
        return $this->accessService->canCreateIndicator($user);
    }

    public function measureIndicator(User $user): bool
    {
        return $this->accessService->canMeasureIndicator($user);
    }

    public function createAction(User $user): bool
    {
        return $this->accessService->canCreateAction($user);
    }

    public function editAction(User $user): bool
    {
        return $this->accessService->canEditAction($user);
    }

    public function closeAction(User $user): bool
    {
        return $this->accessService->canCloseAction($user);
    }

    public function createEvidence(User $user): bool
    {
        return $this->accessService->canCreateEvidence($user);
    }

    public function reviewEvidence(User $user): bool
    {
        return $this->accessService->canReviewEvidence($user);
    }

    public function approveEvidence(User $user): bool
    {
        return $this->accessService->canApproveEvidence($user);
    }

    public function rejectEvidence(User $user): bool
    {
        return $this->accessService->canRejectEvidence($user);
    }

    public function createMilestone(User $user): bool
    {
        return $this->accessService->canCreateMilestone($user);
    }

    public function registerMonitoring(User $user): bool
    {
        return $this->accessService->canRegisterMonitoring($user);
    }

    public function viewReports(User $user): bool
    {
        return $this->accessService->canViewReports($user);
    }

    public function exportReports(User $user): bool
    {
        return $this->accessService->canExportReports($user);
    }
}
