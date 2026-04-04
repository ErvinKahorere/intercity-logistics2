<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DriverLicence extends Model
{
    use HasFactory;

    protected $fillable = [
        'driver_id',
        'licence_type_code',
        'licence_type_name',
        'licence_number',
        'issue_date',
        'expiry_date',
        'document_path',
        'document_original_name',
        'document_mime_type',
        'document_size',
        'verification_status',
        'submitted_at',
        'verified_at',
        'verified_by',
        'rejection_reason',
        'is_primary',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'expiry_date' => 'date',
        'submitted_at' => 'datetime',
        'verified_at' => 'datetime',
        'is_primary' => 'boolean',
    ];

    protected $appends = ['document_url', 'status_summary'];

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function getDocumentUrlAttribute(): ?string
    {
        return $this->document_path ? asset('storage/' . $this->document_path) : null;
    }

    public function getStatusSummaryAttribute(): string
    {
        return match ($this->verification_status) {
            'verified' => 'Verified',
            'rejected' => 'Rejected',
            'expired' => 'Expired',
            default => 'Pending Review',
        };
    }
}
