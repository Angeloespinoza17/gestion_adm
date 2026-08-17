<?php
namespace App\Http\Controllers\SocialWork;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\CourseSection;
use App\Models\SocialWork\FormTemplate;
use App\Models\SocialWork\JunaebBenefitType;
use App\Models\SocialWork\ProgramType;
use App\Models\SocialWork\Protocol;
use App\Models\SocialWork\RiskRule;
use App\Models\SocialWork\SocialCase;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class ConfigurationController extends Controller {
    public function audit(Request $request): JsonResponse { $query=DB::table('social_work_audit_events')->leftJoin('users','users.id','=','social_work_audit_events.user_id')->select(['social_work_audit_events.*','users.name as user_name'])->when($request->query('action'),fn($q,$v)=>$q->where('action',$v))->when($request->query('from'),fn($q,$v)=>$q->whereDate('occurred_at','>=',$v))->when($request->query('to'),fn($q,$v)=>$q->whereDate('occurred_at','<=',$v))->orderByDesc('occurred_at'); return response()->json($query->paginate(min((int)$request->query('per_page',30),100))); }
    public function catalogs(): JsonResponse { return response()->json(['data'=>['case_statuses'=>SocialCase::STATUSES,'risk_levels'=>SocialCase::RISK_LEVELS,'priorities'=>SocialCase::PRIORITIES,'confidentiality'=>SocialCase::CONFIDENTIALITY,'academic_years'=>AcademicYear::orderByDesc('year')->get(['id','name','year','is_active']),'courses'=>CourseSection::where('active',true)->orderBy('display_name')->get(['id','academic_year_id','display_name']),'professionals'=>User::where('active',true)->where('user_type','staff')->orderBy('name')->get(['id','name']),'program_types'=>ProgramType::where('active',true)->orderBy('name')->get(),'junaeb_benefit_types'=>JunaebBenefitType::where('active',true)->orderBy('name')->get(),'protocols'=>Protocol::with('versions')->where('active',true)->get(),'risk_rules'=>RiskRule::where('active',true)->get()]]); }
    public function templates(): JsonResponse { return response()->json(FormTemplate::with('versions')->orderBy('type')->paginate(30)); }
    public function storeTemplate(Request $request): JsonResponse { $data=$request->validate(['code'=>['required','string','max:80','unique:social_work_form_templates,code'],'name'=>['required','string'],'type'=>['required','string'],'schema'=>['required','array'],'confidentiality'=>['required','in:interno,restringido,altamente_restringido'],'print_template'=>['nullable','string']]); return DB::transaction(function()use($data,$request){ $template=FormTemplate::create(['code'=>$data['code'],'name'=>$data['name'],'type'=>$data['type'],'active'=>true,'created_by'=>$request->user()->id]); $template->versions()->create(['version'=>1,'status'=>'borrador','schema'=>$data['schema'],'confidentiality'=>$data['confidentiality'],'print_template'=>$data['print_template']??null,'header'=>'BORRADOR BASE – REQUIERE VALIDACIÓN INSTITUCIONAL','created_by'=>$request->user()->id]); return response()->json(['message'=>'Plantilla borrador versionada.','data'=>$template->load('versions')],201); }); }
    public function protocols(): JsonResponse { return response()->json(Protocol::with('versions')->paginate(30)); }
    public function storeProtocol(Request $request): JsonResponse { $data=$request->validate(['code'=>['required','string','unique:social_work_protocols,code'],'name'=>['required','string'],'description'=>['nullable','string'],'steps'=>['required','array'],'required_documents'=>['nullable','array'],'safeguards'=>['nullable','array']]); return DB::transaction(function()use($data,$request){ $p=Protocol::create(['code'=>$data['code'],'name'=>$data['name'],'description'=>$data['description']??null,'active'=>true,'responsible_user_id'=>$request->user()->id]); $p->versions()->create(['version'=>1,'status'=>'borrador','steps'=>$data['steps'],'required_documents'=>$data['required_documents']??[],'safeguards'=>$data['safeguards']??[],'fields'=>[],'created_by'=>$request->user()->id]); return response()->json(['message'=>'Protocolo creado como borrador versionado.','data'=>$p->load('versions')],201); }); }
}
