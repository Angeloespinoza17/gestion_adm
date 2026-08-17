<?php
namespace App\Events\SocialWork;
use App\Models\SocialWork\SocialCase;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
class SocialCaseStatusChanged { use Dispatchable, SerializesModels; public function __construct(public SocialCase $case, public string $from, public string $to, public User $user) {} }
