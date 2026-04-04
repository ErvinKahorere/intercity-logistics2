<?php

namespace App\Http\Controllers;

use App\Models\Quotation;
use App\Services\PdfDocumentService;
use App\Services\QuotationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class QuotationController extends Controller
{
    public function __construct(
        private QuotationService $quotationService,
        private PdfDocumentService $pdfDocumentService,
    ) {
    }

    public function storePreview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pickup_location_id' => ['required', 'exists:locations,id', 'different:dropoff_location_id'],
            'dropoff_location_id' => ['required', 'exists:locations,id'],
            'package_type_id' => ['required', 'exists:package_types,id'],
            'pickup_address' => ['nullable', 'string', 'max:255'],
            'dropoff_address' => ['nullable', 'string', 'max:255'],
            'receiver_name' => ['nullable', 'string', 'max:255'],
            'receiver_phone' => ['nullable', 'string', 'max:50'],
            'weight_kg' => ['nullable', 'numeric', 'min:0'],
            'load_size' => ['required', 'in:small,medium,large,heavy,oversized'],
            'urgency_level' => ['required', 'in:standard,express,same_day'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'selected_driver_id' => ['nullable', 'exists:drivers,id'],
        ]);

        $quotation = $this->quotationService->createFromPreview($request->user(), $validated);

        return response()->json([
            'message' => 'Quotation generated. Billing notification was processed for SMS-enabled customers.',
            'quotation' => $quotation->load(['pickupLocation', 'dropoffLocation', 'packageType', 'driver.user']),
            'notification_feedback' => [
                'channel' => 'sms',
                'state' => 'processed',
            ],
        ]);
    }

    public function accept(Request $request, Quotation $quotation): JsonResponse
    {
        abort_unless($quotation->user_id === $request->user()->id, 403);

        $quotation = $this->quotationService->accept($quotation);

        return response()->json([
            'message' => 'Quotation accepted.',
            'quotation' => $quotation,
            'invoice' => $quotation->invoice,
        ]);
    }

    public function download(Request $request, Quotation $quotation): Response
    {
        abort_unless($quotation->user_id === $request->user()->id || $request->user()->hasRole('admin'), 403);

        $quotation->load(['pickupLocation', 'dropoffLocation', 'packageType', 'customer', 'driver.user']);

        return $this->pdfDocumentService->download('pdf.quotation', [
            'quotation' => $quotation,
        ], $quotation->quotation_number . '.pdf');
    }
}
