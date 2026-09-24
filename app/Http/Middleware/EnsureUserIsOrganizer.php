<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsOrganizer
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! $request->user()->isOrganizer()) {
            return response()->json([
                'message' => 'Unauthorized. Only organizers can access this resource.',
            ], 403);
        }

        return $next($request);
    }
}
