<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Tenant\StoreTenantRequest;
use App\Http\Resources\Api\V1\TenantResource;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Middleware;
use Spatie\RouteAttributes\Attributes\Post;
use Spatie\RouteAttributes\Attributes\Prefix;
use Symfony\Component\HttpFoundation\Response;

#[Prefix('api/v1/admin/tenants')]
#[Middleware(['auth:sanctum', 'tenant', 'role:System Admin'])]
class TenantController extends Controller
{
    #[Get('')]
    public function index(): AnonymousResourceCollection
    {
        return TenantResource::collection(Tenant::paginate());
    }

    #[Post('')]
    public function store(StoreTenantRequest $request): JsonResponse
    {
        $tenant = Tenant::create($request->validated());

        return (new TenantResource($tenant))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    #[Get('{tenant}')]
    public function show(Tenant $tenant): TenantResource
    {
        return new TenantResource($tenant);
    }
}
