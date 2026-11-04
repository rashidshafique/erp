<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * A record sent from the phone queue carries X-Queue-Id. If the reply was lost the phone sends it again; the second
 * copy is answered as done instead of being saved twice. A refused record frees its id so a corrected copy can go.
 */
class PhoneReplay
{
    public function handle(Request $request, Closure $next): Response
    {
        $id = $request->header('X-Queue-Id');
        if (! $id || ! $request->isMethod('POST') || strlen($id) > 64) {
            return $next($request);
        }

        $key = 'phone-queue:'.$request->user()?->id.':'.$id;
        if (! Cache::add($key, true, now()->addDays(7))) {
            return response()->json(['duplicate' => true]);
        }
        $response = $next($request);
        if ($response->getStatusCode() >= 400) {
            Cache::forget($key);
        }

        return $response;
    }
}
