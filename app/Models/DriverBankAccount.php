<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DriverBankAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'driver_id',
        'status',
        'account_holder_name',
        'bank_name',
        'branch_name',
        'branch_code',
        'account_number',
        'account_number_last4',
        'account_type',
        'payout_reference_name',
        'submitted_at',
        'confirmed_at',
        'confirmed_by',
    ];

    protected $casts = [
        'account_number' => 'encrypted',
        'submitted_at' => 'datetime',
        'confirmed_at' => 'datetime',
    ];

    protected $appends = ['masked_account_number'];

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    public function confirmer()
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function getMaskedAccountNumberAttribute(): string
    {
        $last4 = $this->account_number_last4 ?: substr((string) $this->account_number, -4);

        return $last4 ? '**** **** ' . $last4 : 'Not saved';
    }
}
