<?php

namespace App\Services\Admin;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UserUsageRecorder
{
    public const ACTIVITY_WINDOW_MINUTES = 10;

    private static bool $tableAvailable = false;

    public function recordLogin(User $user): void
    {
        if (! $this->isAvailable()) {
            return;
        }

        $now = now();
        $this->incrementDaily($user->id, $now, login: true);
        Cache::put($this->pulseKey($user->id, $now), true, $now->copy()->addMinutes(self::ACTIVITY_WINDOW_MINUTES));
    }

    public function recordActivity(User $user): void
    {
        if (! $user->active || ! $this->isAvailable()) {
            return;
        }

        $now = now();
        if (! Cache::add($this->pulseKey($user->id, $now), true, $now->copy()->addMinutes(self::ACTIVITY_WINDOW_MINUTES))) {
            return;
        }

        $this->incrementDaily($user->id, $now, login: false);
    }

    private function incrementDaily(int $userId, Carbon $now, bool $login): void
    {
        $values = [
            'user_id' => $userId,
            'usage_date' => $now->toDateString(),
            'login_count' => 0,
            'usage_count' => 0,
            'first_activity_at' => $now,
            'last_activity_at' => $now,
            'last_login_at' => $login ? $now : null,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        DB::table('user_usage_daily')->insertOrIgnore($values);

        $updates = [
            'usage_count' => DB::raw('usage_count + 1'),
            'last_activity_at' => $now,
            'updated_at' => $now,
        ];

        if ($login) {
            $updates['login_count'] = DB::raw('login_count + 1');
            $updates['last_login_at'] = $now;
        }

        DB::table('user_usage_daily')
            ->where('user_id', $userId)
            ->where('usage_date', $now->toDateString())
            ->update($updates);
    }

    private function pulseKey(int $userId, Carbon $now): string
    {
        return sprintf('user-usage-pulse:%d:%s', $userId, $now->toDateString());
    }

    private function isAvailable(): bool
    {
        if (! self::$tableAvailable) {
            self::$tableAvailable = Schema::hasTable('user_usage_daily');
        }

        return self::$tableAvailable;
    }
}
