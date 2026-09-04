<?php

namespace App\Http\Controllers;

use App\Models\SiteInstallation;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;

class PublicSiteInstallationController extends Controller
{
    private const PER_PAGE = 9;

    public function index(): View
    {
        $installations = Schema::hasTable('site_installations')
            ? SiteInstallation::query()
                ->published()
                ->orderedForPublic()
                ->with(['galleryImages' => static fn ($query) => $query
                    ->orderBy('sort_order')
                    ->orderBy('id')])
                ->paginate(self::PER_PAGE)
                ->withQueryString()
            : new LengthAwarePaginator([], 0, self::PER_PAGE);

        return view('public.pages.campus', [
            'installations' => $installations,
        ]);
    }
}
