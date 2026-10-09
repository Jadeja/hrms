<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class InsufficientLeaveBalanceException extends Exception
{
    public function render(): JsonResponse
    {
        return new JsonResponse([
            'message' => $this->getMessage() ?: 'Insufficient leave balance.',
            'errors' => [
                'days' => [$this->getMessage() ?: 'Insufficient leave balance.'],
            ],
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
