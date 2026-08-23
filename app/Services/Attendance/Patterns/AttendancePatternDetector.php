<?php

namespace App\Services\Attendance\Patterns;

interface AttendancePatternDetector
{
    public function detect(AttendanceDataset $dataset): ?DetectedPattern;
}
