<?php

namespace App\Services;

use App\Models\Invoice;

class AdminInvoiceService
{
    public function list(): array
    {
        return Invoice::query()
            ->with(['customer:id,name,email,phone', 'driver.user:id,name', 'parcelRequest:id,tracking_number'])
            ->latest('issue_date')
            ->get()
            ->map(fn (Invoice $invoice) => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'status' => $invoice->status,
                'payment_status' => $invoice->payment_status,
                'issue_date' => optional($invoice->issue_date)->toDateString(),
                'due_date' => optional($invoice->due_date)->toDateString(),
                'booking_reference' => $invoice->booking_reference,
                'tracking_number' => $invoice->tracking_number,
                'customer' => [
                    'name' => $invoice->customer?->name ?? data_get($invoice->customer_snapshot, 'name'),
                    'email' => $invoice->customer?->email ?? data_get($invoice->customer_snapshot, 'email'),
                ],
                'driver_name' => $invoice->driver?->user?->name ?? data_get($invoice->driver_snapshot, 'name', 'Pending assignment'),
                'route' => [
                    'pickup' => data_get($invoice->route_snapshot, 'pickup_city'),
                    'dropoff' => data_get($invoice->route_snapshot, 'dropoff_city'),
                ],
                'total' => $invoice->total,
                'notes' => $invoice->notes,
                'pricing_breakdown' => $invoice->pricing_breakdown ?? [],
                'overdue' => $invoice->due_date && $invoice->payment_status !== 'paid' && now()->startOfDay()->gt($invoice->due_date->startOfDay()),
            ])
            ->values()
            ->all();
    }

    public function updateStatus(Invoice $invoice, array $validated): Invoice
    {
        $invoice->fill([
            'status' => $validated['status'] ?? $invoice->status,
            'payment_status' => $validated['payment_status'] ?? $invoice->payment_status,
            'notes' => $validated['notes'] ?? $invoice->notes,
        ]);

        if (($validated['payment_status'] ?? null) === 'paid' && ! $invoice->paid_at) {
            $invoice->paid_at = now();
        }

        if (($validated['status'] ?? null) === 'cancelled' && ! $invoice->cancelled_at) {
            $invoice->cancelled_at = now();
        }

        $invoice->save();

        return $invoice->fresh();
    }
}
