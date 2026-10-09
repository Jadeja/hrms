<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class OverlappingLeaveException extends Exception
{
    public function render(): JsonResponse
    {
        return new JsonResponse([
            'message' => $this->getMessage() ?: 'Overlapping leave request detected.',
            'errors' => [
                'dates' => [$this->getMessage() ?: 'You already have an existing leave request spanning these dates.'],
            ],
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
