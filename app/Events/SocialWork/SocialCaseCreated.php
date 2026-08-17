<?php
namespace App\Events\SocialWork;
use App\Models\SocialWork\SocialCase;
use App\Models\User;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
class SocialCaseCreated implements ShouldDispatchAfterCommit { use Dispatchable,SerializesModels; public function __construct(public SocialCase $case,public User $user){} }
