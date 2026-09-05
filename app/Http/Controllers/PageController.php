<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Redirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PageController extends Controller
{
    public function home(): View
    {
        $page = Page::query()
            ->published()
            ->where('is_homepage', true)
            ->firstOrFail();

        return view('pages.show', ['page' => $page]);
    }

    public function show(string $slug): View|RedirectResponse
    {
        $page = Page::query()
            ->published()
            ->where('slug', $slug)
            ->where('is_homepage', false)
            ->first();

        if ($page) {
            return view('pages.show', ['page' => $page]);
        }

        $redirect = Redirect::query()->where('from', '/'.ltrim($slug, '/'))->first();

        if ($redirect) {
            return redirect($redirect->to, $redirect->status_code);
        }

        abort(404);
    }
}
