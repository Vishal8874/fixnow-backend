<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Helpers\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->role !== UserRole::ADMIN) {
            return ApiResponse::error('You are not authorized to access this resource.', [], 403);
        }

        return $next($request);
    }
}
