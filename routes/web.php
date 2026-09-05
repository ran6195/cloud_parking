<?php

use App\Http\Controllers\LeadController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PageController::class, 'home'])->name('home');

Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
Route::get('/robots.txt', [RobotsController::class, 'index'])->name('robots');

Route::post('/contatti', [LeadController::class, 'store'])->name('leads.store');

Route::get('/{slug}', [PageController::class, 'show'])
    ->where('slug', '[a-z0-9\-\/]+')
    ->name('page.show');
