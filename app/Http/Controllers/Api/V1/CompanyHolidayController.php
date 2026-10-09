<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Holiday\StoreHolidayRequest;
use App\Http\Resources\Api\V1\CompanyHolidayResource;
use App\Models\CompanyHoliday;
use App\Models\Tenant;
use App\Services\Holiday\CompanyHolidayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\RouteAttributes\Attributes\Delete;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Middleware;
use Spatie\RouteAttributes\Attributes\Post;
use Spatie\RouteAttributes\Attributes\Prefix;
use Symfony\Component\HttpFoundation\Response;

#[Prefix('api/v1/holidays')]
#[Middleware(['auth:sanctum', 'tenant'])]
class CompanyHolidayController extends Controller
{
    #[Get('')]
    public function index(Request $request, CompanyHolidayService $service): AnonymousResourceCollection
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('tenant') ?? Tenant::current();

        $year = $request->has('year') ? (int) $request->input('year') : null;
        $holidays = $service->getHolidays((int) $tenant->id, $year);

        return CompanyHolidayResource::collection($holidays);
    }

    #[Post('', middleware: 'role:System Admin|HR Admin')]
    public function store(StoreHolidayRequest $request, CompanyHolidayService $service): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('tenant') ?? Tenant::current();

        $holiday = $service->createHoliday((int) $tenant->id, $request->validated());

        return (new CompanyHolidayResource($holiday))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    #[Delete('{companyHoliday}', middleware: 'role:System Admin|HR Admin')]
    public function destroy(CompanyHoliday $companyHoliday, CompanyHolidayService $service): Response
    {
        $service->deleteHoliday($companyHoliday);

        return response()->noContent();
    }
}
