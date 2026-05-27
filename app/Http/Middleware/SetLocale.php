<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\Setting;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Domains\Settings\Support\LocaleDetector;

final class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = LocaleDetector::resolve(
            $request->user()?->locale ?? Setting::get('locale'),
        );

        app()->setLocale($locale);

        return $next($request);
    }
}
