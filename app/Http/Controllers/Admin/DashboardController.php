<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminOperationsService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private AdminOperationsService $adminOperationsService,
    ) {
    }

    public function index(): Response
    {
        return Inertia::render('Admin/Dashboard', $this->adminOperationsService->dashboardPayload());
    }
}
