<?php

namespace App\Http\Controllers;

use App\Models\SiteEvent;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;

class PublicSiteEventController extends Controller
{
    public function index(): View
    {
        return view('public.pages.events', [
            'siteEvents' => Schema::hasTable('site_events')
                ? SiteEvent::query()
                    ->published()
                    ->orderByDesc('starts_at')
                    ->orderByDesc('id')
                    ->paginate(10)
                : new LengthAwarePaginator([], 0, 10),
        ]);
    }

    public function show(SiteEvent $siteEvent): View
    {
        abort_unless($siteEvent->status === SiteEvent::STATUS_PUBLISHED, 404);

        return view('public.pages.event-show', [
            'event' => $siteEvent,
            'relatedEvents' => SiteEvent::query()
                ->published()
                ->whereKeyNot($siteEvent->id)
                ->orderByDesc('starts_at')
                ->orderByDesc('id')
                ->limit(3)
                ->get(),
        ]);
    }
}
