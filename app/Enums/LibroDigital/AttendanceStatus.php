<?php

namespace App\Enums\LibroDigital;

enum AttendanceStatus: string
{
    case Present = 'present';
    case Absent = 'absent';
    case Late = 'late';
    case Excused = 'excused';
    case LeftEarly = 'left_early';
    case NotApplicable = 'not_applicable';
}
