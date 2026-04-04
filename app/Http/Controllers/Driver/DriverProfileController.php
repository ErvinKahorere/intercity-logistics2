<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\DriverBankAccount;
use App\Models\DriverLicence;
use App\Models\User;
use App\Services\BankingDetailsService;
use App\Services\DriverVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DriverProfileController extends Controller
{
    public function __construct(
        private DriverVerificationService $verificationService,
        private BankingDetailsService $bankingDetailsService,
    ) {
    }

    public function show(Request $request): Response
    {
        $user = $request->user()->load('driver.primaryLicence', 'driver.licences', 'driver.bankAccount', 'driver.invoices');
        $driver = $this->ensureDriver($user);
        $driver = $this->verificationService->refreshStatus($driver->loadMissing('primaryLicence', 'licences', 'bankAccount', 'invoices.parcelRequest.pickupLocation', 'invoices.parcelRequest.dropoffLocation'));

        return Inertia::render('Driver/Profile/Index', [
            'user' => $user->fresh(),
            'driverProfile' => $this->serializeDriver($driver),
            'licenceTypes' => $this->licenceTypes(),
        ]);
    }

    public function submitVerification(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'licence_type_code' => ['required', 'string', 'max:50'],
            'licence_type_name' => ['required', 'string', 'max:255'],
            'licence_number' => ['nullable', 'string', 'max:100'],
            'issue_date' => ['nullable', 'date'],
            'expiry_date' => ['required', 'date', 'after:today'],
            'licence_document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:4096'],
        ]);

        $driver = $this->ensureDriver($request->user());
        $driver = $this->verificationService->submit($driver, $validated);

        return response()->json([
            'message' => 'Verification submitted successfully.',
            'driverProfile' => $this->serializeDriver($driver),
        ]);
    }

    public function saveBanking(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'account_holder_name' => ['required', 'string', 'max:255'],
            'bank_name' => ['required', 'string', 'max:255'],
            'branch_name' => ['nullable', 'string', 'max:255'],
            'branch_code' => ['nullable', 'string', 'max:50'],
            'account_number' => ['required', 'regex:/^[0-9]{6,20}$/'],
            'account_type' => ['required', 'in:current,savings,business,cheque'],
            'payout_reference_name' => ['nullable', 'string', 'max:255'],
        ]);

        $driver = $this->ensureDriver($request->user());
        $this->bankingDetailsService->save($driver, $validated);

        return response()->json([
            'message' => 'Banking details saved.',
            'driverProfile' => $this->serializeDriver($driver->fresh(['primaryLicence', 'licences', 'bankAccount'])),
        ]);
    }

    public function adminQueue(): JsonResponse
    {
        $rows = DriverLicence::query()
            ->with(['driver.user', 'verifier'])
            ->latest('submitted_at')
            ->get()
            ->map(fn (DriverLicence $licence) => [
                'id' => $licence->id,
                'driver_id' => $licence->driver_id,
                'driver_name' => $licence->driver?->user?->name,
                'driver_email' => $licence->driver?->user?->email,
                'verification_status' => $licence->verification_status,
                'licence_type_name' => $licence->licence_type_name,
                'licence_number' => $licence->licence_number,
                'expiry_date' => optional($licence->expiry_date)->toDateString(),
                'document_url' => $licence->document_url,
                'submitted_at' => optional($licence->submitted_at)->toIso8601String(),
                'rejection_reason' => $licence->rejection_reason,
            ]);

        return response()->json(['items' => $rows]);
    }

    public function reviewVerification(Request $request, DriverLicence $driverLicence): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:verified,rejected'],
            'rejection_reason' => ['nullable', 'required_if:status,rejected', 'string', 'max:1000'],
        ]);

        $licence = $this->verificationService->review(
            $driverLicence,
            $validated['status'],
            $request->user(),
            $validated['rejection_reason'] ?? null
        );

        return response()->json([
            'message' => 'Verification updated.',
            'licence' => [
                'id' => $licence->id,
                'verification_status' => $licence->verification_status,
                'rejection_reason' => $licence->rejection_reason,
                'verified_at' => optional($licence->verified_at)->toIso8601String(),
            ],
        ]);
    }

    private function ensureDriver(User $user): Driver
    {
        return $user->driver ?? Driver::create([
            'user_id' => $user->id,
            'phone' => $user->phone,
            'location' => $user->location,
            'status' => 'active',
        ]);
    }

    private function serializeDriver(Driver $driver): array
    {
        $licences = $driver->licences->map(fn (DriverLicence $licence) => [
            'id' => $licence->id,
            'licence_type_code' => $licence->licence_type_code,
            'licence_type_name' => $licence->licence_type_name,
            'licence_number' => $licence->licence_number,
            'issue_date' => optional($licence->issue_date)->toDateString(),
            'expiry_date' => optional($licence->expiry_date)->toDateString(),
            'verification_status' => $licence->verification_status,
            'status_summary' => $licence->status_summary,
            'document_url' => $licence->document_url,
            'rejection_reason' => $licence->rejection_reason,
            'is_primary' => $licence->is_primary,
        ])->values();

        /** @var DriverBankAccount|null $bankAccount */
        $bankAccount = $driver->bankAccount;

        return [
            'id' => $driver->id,
            'verification_status' => $driver->verification_status,
            'verification_submitted_at' => optional($driver->verification_submitted_at)->toIso8601String(),
            'verification_rejection_reason' => $driver->verification_rejection_reason,
            'verified_at' => optional($driver->verified_at)->toIso8601String(),
            'primary_licence' => $licences->firstWhere('is_primary', true),
            'licences' => $licences,
            'bank_account' => $bankAccount ? [
                'status' => $bankAccount->status,
                'account_holder_name' => $bankAccount->account_holder_name,
                'bank_name' => $bankAccount->bank_name,
                'branch_name' => $bankAccount->branch_name,
                'branch_code' => $bankAccount->branch_code,
                'account_type' => $bankAccount->account_type,
                'payout_reference_name' => $bankAccount->payout_reference_name,
                'masked_account_number' => $bankAccount->masked_account_number,
                'submitted_at' => optional($bankAccount->submitted_at)->toIso8601String(),
            ] : null,
            'accounting' => [
                'invoice_count' => $driver->invoices->count(),
                'issued_total' => round((float) $driver->invoices->sum('total'), 2),
                'paid_total' => round((float) $driver->invoices->where('payment_status', 'paid')->sum('total'), 2),
                'pending_total' => round((float) $driver->invoices->whereIn('payment_status', ['pending', 'manual'])->sum('total'), 2),
                'latest_invoices' => $driver->invoices->take(8)->map(fn ($invoice) => [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'status' => $invoice->status,
                    'payment_status' => $invoice->payment_status,
                    'issue_date' => optional($invoice->issue_date)->toDateString(),
                    'due_date' => optional($invoice->due_date)->toDateString(),
                    'total' => (float) $invoice->total,
                    'tracking_number' => $invoice->tracking_number,
                    'route' => trim(collect([
                        data_get($invoice->route_snapshot, 'pickup_city') ?: $invoice->parcelRequest?->pickupLocation?->name,
                        data_get($invoice->route_snapshot, 'dropoff_city') ?: $invoice->parcelRequest?->dropoffLocation?->name,
                    ])->filter()->join(' -> ')),
                ])->values(),
            ],
        ];
    }

    private function licenceTypes(): array
    {
        return [
            ['code' => 'B', 'label' => 'Code B / Light Motor Vehicle'],
            ['code' => 'C1', 'label' => 'Code C1'],
            ['code' => 'C', 'label' => 'Code C'],
            ['code' => 'CE', 'label' => 'Code CE / Heavy Combination'],
            ['code' => 'G', 'label' => 'Goods / Commercial'],
            ['code' => 'OTHER', 'label' => 'Other Commercial Category'],
        ];
    }
}
