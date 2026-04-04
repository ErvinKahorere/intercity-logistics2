<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminQuotationService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class QuotationOperationsController extends Controller
{
    public function __construct(
        private AdminQuotationService $adminQuotationService,
    ) {
    }

    public function page(): Response
    {
        return Inertia::render('Admin/Quotations/Index', [
            'quotations' => $this->adminQuotationService->list(),
        ]);
    }

    public function data(): JsonResponse
    {
        return response()->json([
            'quotations' => $this->adminQuotationService->list(),
        ]);
    }
}
