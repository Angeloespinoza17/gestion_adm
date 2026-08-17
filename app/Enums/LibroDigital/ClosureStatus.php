<?php

namespace App\Enums\LibroDigital;

enum ClosureStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
    case Reopened = 'reopened';
    case Reclosed = 'reclosed';
}
