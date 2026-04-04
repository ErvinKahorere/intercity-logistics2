<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\DriverLicence;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class DriverVerificationService
{
    public function __construct(
        private SmsService $smsService,
    ) {
    }

    public function submit(Driver $driver, array $payload): Driver
    {
        return DB::transaction(function () use ($driver, $payload) {
            $document = $payload['licence_document'];
            $path = $document->store('driver-licences', 'public');

            $driver->licences()
                ->where('is_primary', true)
                ->update(['is_primary' => false]);

            $driver->licences()->create([
                'licence_type_code' => $payload['licence_type_code'],
                'licence_type_name' => $payload['licence_type_name'],
                'licence_number' => $payload['licence_number'] ?? null,
                'issue_date' => $payload['issue_date'] ?? null,
                'expiry_date' => $payload['expiry_date'],
                'document_path' => $path,
                'document_original_name' => $document instanceof UploadedFile ? $document->getClientOriginalName() : null,
                'document_mime_type' => $document instanceof UploadedFile ? $document->getClientMimeType() : null,
                'document_size' => $document instanceof UploadedFile ? $document->getSize() : null,
                'verification_status' => 'pending',
                'submitted_at' => now(),
                'is_primary' => true,
            ]);

            $driver->update([
                'verification_status' => 'pending',
                'verification_submitted_at' => now(),
                'verification_rejection_reason' => null,
                'verified_at' => null,
                'verified_by' => null,
            ]);

            return $driver->fresh(['licences', 'primaryLicence', 'bankAccount']);
        });
    }

    public function review(DriverLicence $licence, string $status, User $reviewer, ?string $rejectionReason = null): DriverLicence
    {
        $this->assertReviewable($licence, $status);

        return DB::transaction(function () use ($licence, $status, $reviewer, $rejectionReason) {
            $driver = $licence->driver;

            $licence->update([
                'verification_status' => $status,
                'verified_at' => $status === 'verified' ? now() : null,
                'verified_by' => $reviewer->id,
                'rejection_reason' => $status === 'rejected' ? $rejectionReason : null,
            ]);

            $driver->update([
                'verification_status' => $status,
                'verification_rejection_reason' => $status === 'rejected' ? $rejectionReason : null,
                'verified_at' => $status === 'verified' ? now() : null,
                'verified_by' => $reviewer->id,
            ]);

            if ($driver->user) {
                $this->smsService->queueTemplate(
                    $driver->user,
                    $status === 'verified' ? 'driver.verification_approved' : 'driver.verification_rejected',
                    [
                        'reason' => $rejectionReason,
                    ],
                    [
                        'event_type' => 'driver_verification_' . $status,
                        'preference_key' => 'verification_updates',
                        'meta' => [
                            'reviewed_by' => $reviewer->id,
                            'licence_id' => $licence->id,
                        ],
                    ]
                );
            }

            return $licence->fresh(['driver.user', 'verifier']);
        });
    }

    public function refreshStatus(Driver $driver): Driver
    {
        $primaryLicence = $driver->primaryLicence()->first();

        if (! $primaryLicence) {
            $driver->update(['verification_status' => 'unverified']);

            return $driver->fresh(['primaryLicence']);
        }

        if ($primaryLicence->expiry_date && $primaryLicence->expiry_date->isPast()) {
            $primaryLicence->update(['verification_status' => 'expired']);
            $driver->update([
                'verification_status' => 'rejected',
                'verification_rejection_reason' => 'Licence has expired. Please upload a renewed licence.',
                'verified_at' => null,
                'verified_by' => null,
            ]);
        }

        return $driver->fresh(['primaryLicence']);
    }

    public function replaceDocument(DriverLicence $licence, UploadedFile $document): DriverLicence
    {
        return DB::transaction(function () use ($licence, $document) {
            if ($licence->document_path) {
                Storage::disk('public')->delete($licence->document_path);
            }

            $path = $document->store('driver-licences', 'public');

            $licence->update([
                'document_path' => $path,
                'document_original_name' => $document->getClientOriginalName(),
                'document_mime_type' => $document->getClientMimeType(),
                'document_size' => $document->getSize(),
                'verification_status' => 'pending',
                'submitted_at' => now(),
                'rejection_reason' => null,
                'verified_at' => null,
                'verified_by' => null,
            ]);

            $licence->driver->update([
                'verification_status' => 'pending',
                'verification_submitted_at' => now(),
                'verification_rejection_reason' => null,
                'verified_at' => null,
                'verified_by' => null,
            ]);

            return $licence->fresh();
        });
    }

    private function assertReviewable(DriverLicence $licence, string $status): void
    {
        if ($status !== 'verified') {
            return;
        }

        $errors = [];

        if (blank($licence->document_path)) {
            $errors['licence_document'] = 'A licence document is required before approval.';
        }

        if (blank($licence->licence_type_code) || blank($licence->licence_type_name)) {
            $errors['licence_type'] = 'A valid licence type is required before approval.';
        }

        if (! $licence->expiry_date) {
            $errors['expiry_date'] = 'A licence expiry date is required before approval.';
        } elseif ($licence->expiry_date->startOfDay()->lte(now()->startOfDay())) {
            $errors['expiry_date'] = 'Expired licences cannot be approved.';
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }
}
