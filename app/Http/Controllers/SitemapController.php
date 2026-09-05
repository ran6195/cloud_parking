<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\Http\Response;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $sitemap = Sitemap::create();

        Page::query()
            ->published()
            ->where('robots_index', true)
            ->orderBy('is_homepage', 'desc')
            ->each(function (Page $page) use ($sitemap) {
                $sitemap->add(
                    Url::create($page->is_homepage ? '/' : '/'.$page->slug)
                        ->setLastModificationDate($page->updated_at)
                        ->setPriority($page->is_homepage ? 1.0 : 0.7)
                );
            });

        return $sitemap->toResponse(request());
    }
}
