<?php

namespace App\Http\Controllers;

use App\Settings\GeneralSettings;
use Illuminate\Http\Response;

class RobotsController extends Controller
{
    public function index(GeneralSettings $settings): Response
    {
        $body = $settings->robots_txt ?: "User-agent: *\nAllow: /";

        $body .= "\n\nSitemap: ".url('/sitemap.xml');

        return response($body, 200)->header('Content-Type', 'text/plain');
    }
}
