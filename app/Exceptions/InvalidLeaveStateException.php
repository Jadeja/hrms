<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class InvalidLeaveStateException extends Exception
{
    public function render(): JsonResponse
    {
        return new JsonResponse([
            'message' => $this->getMessage() ?: 'Invalid leave request state.',
            'errors' => [
                'status' => [$this->getMessage() ?: 'The leave request cannot be processed in its current status.'],
            ],
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
