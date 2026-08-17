<?php
namespace App\Jobs\SocialWork;
use App\Models\SocialWork\SocialCase;
use App\Models\User;
use App\Services\SocialWork\RiskAssessmentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
class EvaluateStudentRisk implements ShouldQueue {
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public function __construct(public int $studentId, public ?int $caseId, public int $userId) {}
    public function handle(RiskAssessmentService $service): void { $service->evaluate($this->studentId, $this->caseId ? SocialCase::find($this->caseId) : null, User::findOrFail($this->userId), ['evaluation_origin' => 'scheduled']); }
}
