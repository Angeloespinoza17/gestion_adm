<?php

namespace App\Http\Controllers;

use App\Models\NewsPost;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class PublicNewsController extends Controller
{
    public function home(): View
    {
        return view('public.home', [
            'latestNews' => Schema::hasTable('news_posts')
                ? NewsPost::query()
                    ->published()
                    ->latest('created_at')
                    ->latest('id')
                    ->limit(5)
                    ->get()
                : collect(),
        ]);
    }

    public function index(): View
    {
        return view('public.pages.news', [
            'newsPosts' => Schema::hasTable('news_posts')
                ? NewsPost::query()
                    ->published()
                    ->latest('created_at')
                    ->latest('id')
                    ->paginate(9)
                : new LengthAwarePaginator([], 0, 9),
        ]);
    }

    public function show(Request $request, NewsPost $newsPost): View
    {
        abort_unless($this->isPubliclyVisible($newsPost), 404);

        $this->recordView($request, $newsPost);

        return view('public.pages.news-show', [
            'post' => $newsPost,
            'relatedNews' => NewsPost::query()
                ->published()
                ->whereKeyNot($newsPost->id)
                ->orderedForPublic()
                ->limit(3)
                ->get(),
        ]);
    }

    public function image(NewsPost $newsPost)
    {
        abort_unless($newsPost->image_path, 404);
        abort_unless($this->isPubliclyVisible($newsPost) || auth()->check(), 404);
        abort_unless(Storage::disk('public')->exists($newsPost->image_path), 404);

        return Storage::disk('public')->response($newsPost->image_path, null, [
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    private function isPubliclyVisible(NewsPost $newsPost): bool
    {
        return $newsPost->status === NewsPost::STATUS_PUBLISHED
            && $newsPost->published_at !== null
            && $newsPost->published_at->lte(now());
    }

    private function recordView(Request $request, NewsPost $newsPost): void
    {
        $sessionKey = "news_posts.viewed.{$newsPost->id}";

        if ($request->hasSession() && $request->session()->has($sessionKey)) {
            return;
        }

        DB::table('news_posts')
            ->where('id', $newsPost->id)
            ->increment('views_count');

        $newsPost->views_count = ((int) $newsPost->views_count) + 1;

        if ($request->hasSession()) {
            $request->session()->put($sessionKey, now()->toIso8601String());
        }
    }
}
