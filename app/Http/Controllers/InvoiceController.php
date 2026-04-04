<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\PdfDocumentService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InvoiceController extends Controller
{
    public function __construct(
        private PdfDocumentService $pdfDocumentService,
    ) {
    }

    public function download(Request $request, Invoice $invoice): Response
    {
        abort_unless($invoice->user_id === $request->user()->id || $request->user()->hasRole('admin'), 403);

        $invoice->load(['customer', 'driver.user', 'quotation', 'parcelRequest.pickupLocation', 'parcelRequest.dropoffLocation', 'parcelRequest.packageType']);

        return $this->pdfDocumentService->download('pdf.invoice', [
            'invoice' => $invoice,
        ], $invoice->invoice_number . '.pdf');
    }
}
