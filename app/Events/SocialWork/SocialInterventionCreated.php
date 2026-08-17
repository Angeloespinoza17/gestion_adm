<?php
namespace App\Events\SocialWork;
use App\Models\SocialWork\Intervention;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
class SocialInterventionCreated implements ShouldDispatchAfterCommit { use Dispatchable,SerializesModels; public function __construct(public Intervention $intervention){} }
