<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminOperationsService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class RequestOperationsController extends Controller
{
    public function __construct(
        private AdminOperationsService $adminOperationsService,
    ) {
    }

    public function page(): Response
    {
        return Inertia::render('Admin/Requests/Index', [
            'requests' => $this->adminOperationsService->requestsPayload(),
        ]);
    }

    public function data(): JsonResponse
    {
        return response()->json([
            'requests' => $this->adminOperationsService->requestsPayload(),
        ]);
    }
}
