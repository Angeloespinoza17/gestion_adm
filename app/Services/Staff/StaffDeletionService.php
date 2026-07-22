<?php

namespace App\Services\Staff;

use App\Models\Staff;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class StaffDeletionService
{
    public function deleteStaff(Staff $staff): void
    {
        $staffId = (int) $staff->getKey();

        DB::transaction(function () use ($staffId): void {
            $staff = Staff::query()->lockForUpdate()->findOrFail($staffId);
            $linkedUser = User::query()
                ->where('staff_id', $staffId)
                ->lockForUpdate()
                ->first();

            $linkedUser?->delete();
            $this->deleteStaffRecord($staff);
        });

        $this->cleanupDirectories([$staffId]);
    }

    public function deleteStaffRecord(Staff $staff): void
    {
        $staff->dependencyReservations()->delete();
        $staff->delete();
    }

    /**
     * @param  array<int, int>  $staffIds
     */
    public function cleanupDirectories(array $staffIds): void
    {
        foreach (array_unique($staffIds) as $staffId) {
            Storage::disk('public')->deleteDirectory(sprintf('staff/%d', $staffId));
        }
    }
}
