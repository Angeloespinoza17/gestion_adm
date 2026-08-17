<?php
namespace App\Services\SocialWork;
use App\Models\SocialWork\Document;
use App\Models\SocialWork\MedicalCertificate;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
class PrivateDocumentService {
    public function __construct(private readonly AuditService $audit) {}
    public function store(UploadedFile $file, array $data, User $user): Document {
        $path = $file->store('social-work/documents/'.now()->format('Y/m'), 'local');
        $document = Document::create(array_merge($data, ['private_path' => $path, 'original_name' => $file->getClientOriginalName(), 'mime_type' => $file->getMimeType(), 'size_bytes' => $file->getSize(), 'sha256' => hash_file('sha256', $file->getRealPath()), 'uploaded_by' => $user->id]));
        $this->audit->record('document.uploaded', $document, $user, [], ['original_name' => $document->original_name, 'sha256' => $document->sha256]);
        return $document;
    }
    public function storeCertificate(UploadedFile $file, array $data, User $user): MedicalCertificate {
        $path = $file->store('social-work/medical-certificates/'.now()->format('Y/m'), 'local');
        $certificate = MedicalCertificate::create(array_merge($data, ['private_path' => $path, 'original_name' => $file->getClientOriginalName(), 'mime_type' => $file->getMimeType(), 'size_bytes' => $file->getSize(), 'sha256' => hash_file('sha256', $file->getRealPath()), 'uploaded_by' => $user->id]));
        $this->audit->record('medical_certificate.uploaded', $certificate, $user, [], ['original_name' => $certificate->original_name, 'sha256' => $certificate->sha256]);
        return $certificate;
    }
    public function download(Document|MedicalCertificate $document, User $user) {
        $this->audit->record('document.downloaded', $document, $user, [], ['original_name' => $document->original_name]);
        abort_unless(Storage::disk('local')->exists($document->getRawOriginal('private_path')), 404);
        return Storage::disk('local')->download($document->getRawOriginal('private_path'), $document->original_name, ['Content-Type' => $document->mime_type, 'Cache-Control' => 'private, no-store']);
    }
}
