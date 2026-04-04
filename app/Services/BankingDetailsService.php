<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\DriverBankAccount;

class BankingDetailsService
{
    public function save(Driver $driver, array $payload): DriverBankAccount
    {
        return DriverBankAccount::query()->updateOrCreate(
            ['driver_id' => $driver->id],
            [
                'status' => 'submitted',
                'account_holder_name' => $payload['account_holder_name'],
                'bank_name' => $payload['bank_name'],
                'branch_name' => $payload['branch_name'] ?? null,
                'branch_code' => $payload['branch_code'] ?? null,
                'account_number' => $payload['account_number'],
                'account_number_last4' => substr((string) $payload['account_number'], -4),
                'account_type' => $payload['account_type'],
                'payout_reference_name' => $payload['payout_reference_name'] ?? null,
                'submitted_at' => now(),
            ]
        );
    }
}
