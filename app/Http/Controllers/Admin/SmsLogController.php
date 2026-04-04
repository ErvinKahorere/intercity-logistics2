<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminSmsLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SmsLogController extends Controller
{
    public function __construct(
        private AdminSmsLogService $smsLogService,
    ) {
    }

    public function page(Request $request): Response
    {
        return Inertia::render('Admin/SmsLogs/Index', [
            'smsLogs' => $this->smsLogService->list($request->only(['status', 'provider', 'search'])),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        return response()->json([
            'smsLogs' => $this->smsLogService->list($request->only(['status', 'provider', 'search'])),
        ]);
    }
}
