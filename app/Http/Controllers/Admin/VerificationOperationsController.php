<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DriverLicence;
use App\Services\AdminDriverVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VerificationOperationsController extends Controller
{
    public function __construct(
        private AdminDriverVerificationService $adminDriverVerificationService,
    ) {
    }

    public function page(): Response
    {
        return Inertia::render('Admin/Verification/Index', [
            'drivers' => $this->adminDriverVerificationService->drivers(),
            'queue' => $this->adminDriverVerificationService->queue(),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $status = $request->string('status')->value();

        return response()->json([
            'drivers' => $this->adminDriverVerificationService->drivers($status ?: null),
            'queue' => $this->adminDriverVerificationService->queue($status ?: null),
        ]);
    }

    public function review(Request $request, DriverLicence $driverLicence): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:verified,rejected'],
            'rejection_reason' => ['nullable', 'required_if:status,rejected', 'string', 'max:1000'],
        ]);

        $licence = $this->adminDriverVerificationService->review(
            $driverLicence,
            $validated['status'],
            $request->user(),
            $validated['rejection_reason'] ?? null
        );

        return response()->json([
            'message' => 'Verification status updated.',
            'licence' => [
                'id' => $licence->id,
                'verification_status' => $licence->verification_status,
                'rejection_reason' => $licence->rejection_reason,
            ],
            'drivers' => $this->adminDriverVerificationService->drivers(),
            'queue' => $this->adminDriverVerificationService->queue(),
        ]);
    }
}
