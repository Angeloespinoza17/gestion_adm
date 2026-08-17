<?php

namespace App\Services\LibroDigital;

use App\Exceptions\LibroDigital\LibroDigitalException;
use App\Models\LibroDigital\Book;

class SubsidyAttendanceResolver
{
    public function __construct(private readonly AttendancePolicyResolver $policies) {}

    public function resolve(Book $book, int $teachingGroupId, string $date): never
    {
        $policy = $this->policies->for($book->regulatoryProfile);
        throw new LibroDigitalException(
            'La asistencia para subvención no se derivará hasta configurar y aprobar su regla oficial.',
            'COMPLIANCE_BLOCKER_SUBSIDY_ATTENDANCE_RULE',
            409,
            [['configured_policy' => $policy['subsidy_resolution']]],
        );
    }
}
