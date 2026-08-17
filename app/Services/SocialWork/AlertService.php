<?php

namespace App\Services\SocialWork;

use App\Models\SocialWork\Alert;
use App\Models\User;
use App\Notifications\SocialWork\SocialWorkAlertNotification;

class AlertService
{
    public function raise(array $attributes): Alert
    {
        $alert = Alert::firstOrCreate(
            ['deduplication_key' => $attributes['deduplication_key']],
            array_merge($attributes, ['alerted_at' => $attributes['alerted_at'] ?? now(), 'status' => $attributes['status'] ?? 'nueva'])
        );
        if ($alert->wasRecentlyCreated && $alert->responsible_user_id && ($user = User::find($alert->responsible_user_id))) {
            $user->notify(new SocialWorkAlertNotification($alert));
        }
        return $alert;
    }
}
