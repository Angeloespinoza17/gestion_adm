<?php

namespace App\Policies;

use App\Models\Psychology\PsychologyDocument;
use App\Models\User;
use App\Services\Psychology\PsychologyAccessService;

class PsychologyDocumentPolicy
{
    public function __construct(private readonly PsychologyAccessService $access) {}

    public function view(User $user, PsychologyDocument $document): bool
    {
        if (! $user->hasPermission('psychology.documents.download')) {
            return false;
        }
        if ($document->case) {
            return $this->access->canViewCase($user, $document->case);
        }

        return $document->referral && $this->access->canViewReferral($user, $document->referral);
    }
}
