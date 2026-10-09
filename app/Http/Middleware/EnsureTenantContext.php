<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantContext
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return new JsonResponse([
                'message' => 'Unauthenticated.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $headerTenantId = $request->header('X-Tenant-ID');

        if ($user->isSystemAdmin()) {
            if ($headerTenantId !== null && $headerTenantId !== '') {
                $tenant = Tenant::find($headerTenantId);
                if (! $tenant) {
                    return new JsonResponse([
                        'message' => 'Requested tenant not found.',
                    ], Response::HTTP_NOT_FOUND);
                }
                $tenant->makeCurrent();
                $request->attributes->set('tenant', $tenant);

                return $next($request);
            }

            if ($user->tenant_id !== null) {
                $tenant = Tenant::find($user->tenant_id);
                $tenant?->makeCurrent();
                $request->attributes->set('tenant', $tenant);
            }

            return $next($request);
        }

        if ($user->tenant_id === null) {
            return new JsonResponse([
                'message' => 'User does not belong to any tenant.',
            ], Response::HTTP_FORBIDDEN);
        }

        if ($headerTenantId !== null && $headerTenantId !== '' && (string) $headerTenantId !== (string) $user->tenant_id) {
            return new JsonResponse([
                'message' => 'Tenant access denied: Token does not belong to requested tenant.',
            ], Response::HTTP_FORBIDDEN);
        }

        $tenant = Tenant::find($user->tenant_id);
        if (! $tenant) {
            return new JsonResponse([
                'message' => 'Associated tenant not found.',
            ], Response::HTTP_FORBIDDEN);
        }

        $tenant->makeCurrent();
        $request->attributes->set('tenant', $tenant);

        return $next($request);
    }
}
