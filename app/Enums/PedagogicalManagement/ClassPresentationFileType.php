<?php

namespace App\Enums\PedagogicalManagement;

enum ClassPresentationFileType: string
{
    case PowerPoint = 'pptx';
    case Pdf = 'pdf';
    case TeacherGuidePdf = 'teacher_guide_pdf';
    case Preview = 'preview';
    case Thumbnail = 'thumbnail';
    case Json = 'json';
}
