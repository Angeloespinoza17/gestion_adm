<?php
namespace App\Http\Controllers\SocialWork;
use App\Http\Controllers\Controller;
use App\Models\SocialWork\Document;
use App\Models\SocialWork\MedicalCertificate;
use App\Services\SocialWork\PrivateDocumentService;
use Illuminate\Http\Request;
class DocumentController extends Controller {
    public function certificates(Request $request) { return response()->json(MedicalCertificate::with('student:id,first_name,last_name,registered_name,rut')->when($request->query('student_profile_id'),fn($q,$v)=>$q->where('student_profile_id',$v))->when($request->query('status'),fn($q,$v)=>$q->where('status',$v))->latest('issued_on')->paginate(30)); }
    public function store(Request $request, PrivateDocumentService $service) { $data=$request->validate(['file'=>['required','file','mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx','max:10240'],'student_profile_id'=>['nullable','exists:student_profiles,id'],'case_id'=>['nullable','exists:social_work_cases,id'],'category'=>['required','string','max:80'],'description'=>['nullable','string'],'confidentiality'=>['required','in:interno,restringido,altamente_restringido'],'valid_until'=>['nullable','date']]); $file=$data['file']; unset($data['file']); return response()->json(['message'=>'Documento guardado en almacenamiento privado.','data'=>$service->store($file,$data,$request->user())],201); }
    public function download(Request $request, Document $document, PrivateDocumentService $service) { abort_unless($request->user()->hasPermission('social_work.confidential.view') || $document->confidentiality==='interno',403); return $service->download($document,$request->user()); }
    public function storeCertificate(Request $request, PrivateDocumentService $service) { $data=$request->validate(['file'=>['required','file','mimes:pdf,jpg,jpeg,png','max:10240'],'student_profile_id'=>['required','exists:student_profiles,id'],'case_id'=>['nullable','exists:social_work_cases,id'],'issued_on'=>['required','date'],'covers_from'=>['nullable','date'],'covers_to'=>['nullable','date','after_or_equal:covers_from'],'issuer'=>['nullable','string'],'certificate_type'=>['required','string'],'administrative_summary'=>['nullable','string'],'school_restrictions'=>['nullable','string'],'expires_on'=>['nullable','date'],'status'=>['sometimes','string']]); $file=$data['file']; unset($data['file']); return response()->json(['message'=>'Certificado guardado en almacenamiento privado.','data'=>$service->storeCertificate($file,$data,$request->user())],201); }
    public function downloadCertificate(Request $request, MedicalCertificate $certificate, PrivateDocumentService $service) { abort_unless($request->user()->hasPermission('social_work.medical_documents.view'),403); return $service->download($certificate,$request->user()); }
}
