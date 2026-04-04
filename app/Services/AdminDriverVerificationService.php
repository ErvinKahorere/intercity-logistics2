<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\DriverLicence;
use App\Models\User;

class AdminDriverVerificationService
{
    public function __construct(
        private DriverVerificationService $driverVerificationService,
    ) {
    }

    public function queue(?string $status = null): array
    {
        return DriverLicence::query()
            ->with(['driver.user', 'driver.bankAccount', 'driver.driverRoutes.locations', 'driver.driverRoutes'])
            ->when($status && $status !== 'all', fn ($query) => $query->where('verification_status', $status))
            ->latest('submitted_at')
            ->get()
            ->map(fn (DriverLicence $licence) => $this->serializeLicence($licence))
            ->values()
            ->all();
    }

    public function drivers(?string $status = null): array
    {
        return Driver::query()
            ->with(['user', 'primaryLicence', 'bankAccount', 'driverRoutes.locations', 'driverRoutes.packages'])
            ->when($status && $status !== 'all', fn ($query) => $query->where('verification_status', $status))
            ->orderByDesc('verified_at')
            ->orderByDesc('verification_submitted_at')
            ->get()
            ->map(function (Driver $driver) {
                $primaryRoute = $driver->driverRoutes->first();
                $locations = $primaryRoute?->locations?->pluck('name')->values() ?? collect();

                return [
                    'id' => $driver->id,
                    'user_id' => $driver->user_id,
                    'name' => $driver->user?->name,
                    'email' => $driver->user?->email,
                    'phone' => $driver->phone ?: $driver->user?->phone,
                    'verification_status' => $driver->verification_status,
                    'verification_rejection_reason' => $driver->verification_rejection_reason,
                    'verified_at' => optional($driver->verified_at)->toIso8601String(),
                    'vehicle_type' => $primaryRoute?->vehicle_type,
                    'routes_served' => $locations->all(),
                    'parcel_capabilities' => $primaryRoute?->packages?->pluck('name')->values()->all() ?? [],
                    'primary_licence' => $driver->primaryLicence ? $this->serializeLicence($driver->primaryLicence) : null,
                    'banking_status' => $driver->bankAccount?->status ?? 'incomplete',
                    'masked_account_number' => $driver->bankAccount?->masked_account_number,
                ];
            })
            ->values()
            ->all();
    }

    public function review(DriverLicence $licence, string $status, User $reviewer, ?string $reason = null): DriverLicence
    {
        return $this->driverVerificationService->review($licence, $status, $reviewer, $reason);
    }

    private function serializeLicence(DriverLicence $licence): array
    {
        $driver = $licence->driver;
        $route = $driver?->driverRoutes?->first();
        $daysToExpiry = $licence->expiry_date ? now()->startOfDay()->diffInDays($licence->expiry_date->startOfDay(), false) : null;

        return [
            'id' => $licence->id,
            'driver_id' => $licence->driver_id,
            'driver_name' => $driver?->user?->name,
            'driver_email' => $driver?->user?->email,
            'driver_phone' => $driver?->phone ?: $driver?->user?->phone,
            'verification_status' => $licence->verification_status,
            'licence_type_name' => $licence->licence_type_name,
            'licence_type_code' => $licence->licence_type_code,
            'licence_number' => $licence->licence_number,
            'issue_date' => optional($licence->issue_date)->toDateString(),
            'expiry_date' => optional($licence->expiry_date)->toDateString(),
            'document_url' => $licence->document_url,
            'submitted_at' => optional($licence->submitted_at)->toIso8601String(),
            'verified_at' => optional($licence->verified_at)->toIso8601String(),
            'rejection_reason' => $licence->rejection_reason,
            'days_to_expiry' => $daysToExpiry,
            'expiry_state' => $daysToExpiry === null ? 'unknown' : ($daysToExpiry < 0 ? 'expired' : ($daysToExpiry <= 30 ? 'expiring' : 'valid')),
            'banking_status' => $driver?->bankAccount?->status ?? 'incomplete',
            'masked_account_number' => $driver?->bankAccount?->masked_account_number,
            'vehicle_type' => $route?->vehicle_type,
            'routes_served' => $driver?->driverRoutes?->flatMap(fn ($item) => $item->locations->pluck('name'))->unique()->values()->all() ?? [],
            'parcel_capabilities' => $driver?->driverRoutes?->flatMap(fn ($item) => $item->packages->pluck('name'))->unique()->values()->all() ?? [],
            'audit_trail' => array_values(array_filter([
                $licence->submitted_at ? ['label' => 'Submitted', 'time' => $licence->submitted_at->diffForHumans(), 'meta' => 'Licence uploaded for review'] : null,
                $licence->verified_at ? ['label' => ucfirst($licence->verification_status), 'time' => $licence->verified_at->diffForHumans(), 'meta' => $licence->rejection_reason ?: 'Verification reviewed by operations'] : null,
            ])),
            'missing_fields' => collect([
                blank($licence->licence_type_name) ? 'Licence type missing' : null,
                blank($licence->licence_number) ? 'Licence number missing' : null,
                blank($licence->document_path) ? 'Document missing' : null,
                blank($licence->expiry_date) ? 'Expiry date missing' : null,
            ])->filter()->values()->all(),
        ];
    }
}
