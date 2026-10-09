<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\Scopes\TenantScope;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Hash;

final readonly class LoginService
{
    /**
     * @return array{user: User, token: string}
     *
     * @throws AuthenticationException
     */
    public function execute(string $email, string $password): array
    {
        // When logging in, search across tenants
        $user = User::withoutGlobalScope(TenantScope::class)
            ->where('email', $email)
            ->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw new AuthenticationException('The provided credentials do not match our records.');
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
        ];
    }
}
