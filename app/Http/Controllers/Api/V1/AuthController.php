<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Services\Auth\LoginService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Post;
use Spatie\RouteAttributes\Attributes\Prefix;
use Symfony\Component\HttpFoundation\Response;

#[Prefix('api/v1/auth')]
class AuthController extends Controller
{
    #[Post('login')]
    public function login(LoginRequest $request, LoginService $loginService): JsonResponse
    {
        $result = $loginService->execute(
            $request->string('email')->value(),
            $request->string('password')->value()
        );

        return (new UserResource($result['user']))
            ->additional([
                'meta' => [
                    'token' => $result['token'],
                    'token_type' => 'Bearer',
                ],
            ])
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    #[Post('logout', middleware: ['auth:sanctum', 'tenant'])]
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user) {
            $user->currentAccessToken()?->delete();
        }

        return new JsonResponse([
            'message' => 'Successfully logged out.',
        ], Response::HTTP_OK);
    }

    #[Get('me', middleware: ['auth:sanctum', 'tenant'])]
    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }
}
