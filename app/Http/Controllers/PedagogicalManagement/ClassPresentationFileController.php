<?php

namespace App\Http\Controllers\PedagogicalManagement;

use App\Enums\PedagogicalManagement\ClassPresentationFileType;
use App\Http\Controllers\Controller;
use App\Models\PedagogicalManagement\ClassPresentation;
use App\Models\PedagogicalManagement\ClassPresentationFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class ClassPresentationFileController extends Controller
{
    public function download(ClassPresentation $presentation, ClassPresentationFile $file): Response
    {
        $this->authorize('download', $presentation);
        abort_unless((int) $file->class_presentation_id === (int) $presentation->id, 404);
        $disk = Storage::disk($file->disk);
        abort_unless($disk->exists($file->path), 404, 'El archivo solicitado ya no está disponible.');
        $inline = request()->boolean('inline') && in_array($file->type, [ClassPresentationFileType::Preview, ClassPresentationFileType::Thumbnail], true);
        if ((string) config("filesystems.disks.{$file->disk}.driver") === 's3') {
            return redirect()->away($disk->temporaryUrl($file->path, now()->addMinutes(5), ['ResponseContentDisposition' => ($inline ? 'inline' : 'attachment').'; filename="'.$file->filename.'"']));
        }

        return $inline
            ? $disk->response($file->path, $file->filename, ['Content-Type' => $file->mime_type, 'Content-Disposition' => 'inline; filename="'.$file->filename.'"'])
            : $disk->download($file->path, $file->filename, ['Content-Type' => $file->mime_type]);
    }
}
