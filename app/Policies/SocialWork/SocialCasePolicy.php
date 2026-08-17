<?php
namespace App\Policies\SocialWork;
use App\Models\SocialWork\SocialCase;
use App\Models\User;
use App\Services\SocialWork\AccessService;
class SocialCasePolicy {
    public function viewAny(User $user): bool { return $user->hasPermission('social_work.cases.view'); }
    public function view(User $user, SocialCase $case): bool { return $user->hasPermission('social_work.cases.view') && app(AccessService::class)->canViewCase($user,$case); }
    public function create(User $user): bool { return $user->hasPermission('social_work.cases.create'); }
    public function update(User $user, SocialCase $case): bool { return $user->hasPermission('social_work.cases.update') && app(AccessService::class)->canViewCase($user,$case) && ($case->status!=='cerrado'||$user->hasPermission('social_work.closed_case.correct')); }
    public function close(User $user, SocialCase $case): bool { return $user->hasPermission('social_work.cases.close') && app(AccessService::class)->canViewCase($user,$case); }
    public function reopen(User $user, SocialCase $case): bool { return $user->hasPermission('social_work.cases.reopen') && $case->status==='cerrado' && app(AccessService::class)->canViewCase($user,$case); }
}
