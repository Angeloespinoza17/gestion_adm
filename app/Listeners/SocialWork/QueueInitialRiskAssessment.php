<?php
namespace App\Listeners\SocialWork;
use App\Events\SocialWork\SocialCaseCreated;
use App\Jobs\SocialWork\EvaluateStudentRisk;
class QueueInitialRiskAssessment { public function handle(SocialCaseCreated $event): void { EvaluateStudentRisk::dispatch($event->case->primary_student_id,$event->case->id,$event->user->id); } }
