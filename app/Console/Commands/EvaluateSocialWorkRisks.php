<?php
namespace App\Console\Commands;
use App\Jobs\SocialWork\EvaluateStudentRisk;
use App\Models\SocialWork\SocialCase;
use Illuminate\Console\Command;
class EvaluateSocialWorkRisks extends Command {
    protected $signature='social-work:evaluate-risks {--sync : Ejecutar en el proceso actual}';
    protected $description='Evalúa reglas configurables para casos sociales activos y genera alertas sin duplicarlas.';
    public function handle(): int { $count=0; SocialCase::query()->whereNotIn('status',['cerrado','anulado'])->whereNotNull('responsible_user_id')->select(['id','primary_student_id','responsible_user_id'])->chunkById(100,function($cases)use(&$count){foreach($cases as $case){$job=new EvaluateStudentRisk($case->primary_student_id,$case->id,$case->responsible_user_id);$this->option('sync')?app()->call([$job,'handle']):dispatch($job);$count++;}});$this->info("Evaluaciones programadas: {$count}");return self::SUCCESS; }
}
