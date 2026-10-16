<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shared-secret auth for server-to-server transfer endpoints.
 * The sender presents the TRANSFER_API_TOKEN configured here.
 */
class TransferTokenMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = trim((string) config('transfer.api_token'));
        $given = trim((string) $request->bearerToken());

        if ($expected === '' || $given === '' || ! hash_equals($expected, $given)) {
            return response()->json(['message' => 'Não autorizado.'], 401);
        }

        return $next($request);
    }
}
