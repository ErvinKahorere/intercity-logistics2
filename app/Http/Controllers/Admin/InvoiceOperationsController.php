<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\AdminInvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceOperationsController extends Controller
{
    public function __construct(
        private AdminInvoiceService $adminInvoiceService,
    ) {
    }

    public function page(): Response
    {
        return Inertia::render('Admin/Invoices/Index', [
            'invoices' => $this->adminInvoiceService->list(),
        ]);
    }

    public function data(): JsonResponse
    {
        return response()->json([
            'invoices' => $this->adminInvoiceService->list(),
        ]);
    }

    public function update(Request $request, Invoice $invoice): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'in:issued,unpaid,partially_paid,paid,cancelled'],
            'payment_status' => ['nullable', 'in:pending,manual,paid,failed'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $invoice = $this->adminInvoiceService->updateStatus($invoice, $validated);

        return response()->json([
            'message' => 'Invoice updated.',
            'invoice' => $invoice,
            'invoices' => $this->adminInvoiceService->list(),
        ]);
    }
}
