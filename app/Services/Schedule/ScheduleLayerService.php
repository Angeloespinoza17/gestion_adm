<?php

namespace App\Services\Schedule;

use App\Models\Schedule\TeacherScheduleLayer;

class ScheduleLayerService
{
    public function createOrUpdate(array $payload, ?TeacherScheduleLayer $layer = null): TeacherScheduleLayer
    {
        $layer = $layer
            ? tap($layer)->update($payload)
            : TeacherScheduleLayer::query()->create($payload);

        return $layer->fresh(['teacher:id,full_name', 'academicYear:id,name,year,is_active']);
    }

    public function toggleVisibility(TeacherScheduleLayer $layer, bool $visible): TeacherScheduleLayer
    {
        $layer->update(['visible_by_default' => $visible]);

        return $layer->fresh();
    }
}
