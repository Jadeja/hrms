<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\User\StoreUserRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\Tenant;
use App\Models\User;
use App\Services\User\CreateUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Middleware;
use Spatie\RouteAttributes\Attributes\Post;
use Spatie\RouteAttributes\Attributes\Prefix;
use Symfony\Component\HttpFoundation\Response;

#[Prefix('api/v1/users')]
#[Middleware(['auth:sanctum', 'tenant', 'role:System Admin|HR Admin'])]
class UserController extends Controller
{
    #[Get('')]
    public function index(Request $request): AnonymousResourceCollection
    {
        $users = User::with('roles')
            ->when($request->query('role'), function ($query, $role): void {
                $query->role($role);
            })
            ->paginate();

        return UserResource::collection($users);
    }

    #[Post('')]
    public function store(StoreUserRequest $request, CreateUserService $createUserService): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('tenant') ?? Tenant::current();

        $user = $createUserService->execute(
            $request->validated(),
            (int) $tenant->id
        );

        return (new UserResource($user))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    #[Get('{user}')]
    public function show(User $user): UserResource
    {
        return new UserResource($user->load('roles'));
    }
}
