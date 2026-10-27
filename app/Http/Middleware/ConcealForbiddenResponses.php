<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ConcealForbiddenResponses
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        return $response->getStatusCode() === 403 ? self::notFound($request) : $response;
    }

    public static function notFound(Request $request): Response
    {
        $headers = ['Cache-Control' => 'no-store, private'];

        if ($request->is('api/*')) {
            return response()->json(['success' => false, 'message' => 'Not Found'], 404, $headers);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Not Found'], 404, $headers);
        }

        return response()->view('errors.page', ['status' => 404], 404, $headers);
    }
}
