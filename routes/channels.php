<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

$messagingRealtimeEnabled = fn (): bool => (bool) config('messaging.enabled')
    && (bool) config('messaging.realtime.enabled');

Broadcast::channel('messaging.user.{id}', fn ($user, $id) => $messagingRealtimeEnabled()
    && $user->canUseMessaging()
    && (int) $user->id === (int) $id);
