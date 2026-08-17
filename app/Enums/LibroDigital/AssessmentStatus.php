<?php

namespace App\Enums\LibroDigital;

enum AssessmentStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case ResultsOpen = 'results_open';
    case Closed = 'closed';
    case Amended = 'amended';
    case Cancelled = 'cancelled';
}
