<?php

namespace App\Services;

use App\Models\CityRoute;
use App\Models\Driver;
use App\Models\DriverLicence;
use App\Models\Invoice;
use App\Models\ParcelRequest;
use App\Models\Quotation;

class AdminOperationsService
{
    public function __construct(
        private PricingRulesService $pricingRulesService,
        private AdminDriverVerificationService $adminDriverVerificationService,
        private RouteManagementService $routeManagementService,
        private AdminQuotationService $adminQuotationService,
        private AdminInvoiceService $adminInvoiceService,
    ) {
    }

    public function dashboardPayload(): array
    {
        $pendingVerifications = DriverLicence::query()->where('verification_status', 'pending')->count();
        $verifiedDrivers = Driver::query()->where('verification_status', 'verified')->count();
        $expiringLicences = DriverLicence::query()->whereDate('expiry_date', '<=', now()->addDays(30)->toDateString())->count();
        $activeRoutes = CityRoute::query()->where('is_active', true)->count();
        $routesMissingDistance = CityRoute::query()->where(function ($query) {
            $query->whereNull('distance_km')->orWhere('distance_km', '<=', 0);
        })->count();
        $activeQuotations = Quotation::query()->whereIn('status', ['draft', 'issued', 'accepted'])->count();
        $unpaidInvoices = Invoice::query()->whereNotIn('payment_status', ['paid'])->where('status', '!=', 'cancelled')->count();
        $requestsToday = ParcelRequest::query()->whereDate('created_at', today())->count();
        $activeDeliveries = ParcelRequest::query()->whereIn('status', ['accepted', 'picked_up', 'in_transit', 'arrived'])->count();
        $pricingAlerts = $this->pricingRulesService->ruleAlerts();

        return [
            'stats' => [
                ['label' => 'Pending Driver Verifications', 'value' => $pendingVerifications, 'meta' => 'Ready for review', 'tone' => $pendingVerifications > 0 ? 'warning' : 'success'],
                ['label' => 'Verified Drivers', 'value' => $verifiedDrivers, 'meta' => 'Customer-ready trust cues', 'tone' => 'success'],
                ['label' => 'Expiring Licences', 'value' => $expiringLicences, 'meta' => '30-day watch window', 'tone' => $expiringLicences > 0 ? 'warning' : 'neutral'],
                ['label' => 'Active Routes', 'value' => $activeRoutes, 'meta' => 'Operational lanes enabled', 'tone' => 'brand'],
                ['label' => 'Routes Missing Distance Data', 'value' => $routesMissingDistance, 'meta' => 'Pricing fallback risk', 'tone' => $routesMissingDistance > 0 ? 'danger' : 'success'],
                ['label' => 'Active Quotations', 'value' => $activeQuotations, 'meta' => 'Open commercial documents', 'tone' => 'brand'],
                ['label' => 'Unpaid Invoices', 'value' => $unpaidInvoices, 'meta' => 'Needs payment follow-up', 'tone' => $unpaidInvoices > 0 ? 'warning' : 'success'],
                ['label' => 'Requests Today', 'value' => $requestsToday, 'meta' => 'New marketplace demand', 'tone' => 'neutral'],
                ['label' => 'Active Deliveries', 'value' => $activeDeliveries, 'meta' => 'In live execution', 'tone' => 'brand'],
            ],
            'alerts' => array_values(array_filter([
                $pendingVerifications > 0 ? ['title' => "{$pendingVerifications} drivers pending approval", 'description' => 'Review licence documents and verification details.', 'tone' => 'warning'] : null,
                $expiringLicences > 0 ? ['title' => "{$expiringLicences} licences expiring soon", 'description' => 'Drivers may need updated documentation to remain operational.', 'tone' => 'warning'] : null,
                $routesMissingDistance > 0 ? ['title' => "{$routesMissingDistance} routes missing distance values", 'description' => 'Pricing may fall back to approximate calculations on these lanes.', 'tone' => 'danger'] : null,
                $unpaidInvoices > 0 ? ['title' => "{$unpaidInvoices} invoices unpaid", 'description' => 'Commercial follow-up is required on issued billing documents.', 'tone' => 'warning'] : null,
                count($pricingAlerts['missing_parcel_type_rules']) > 0 ? ['title' => 'Pricing rules incomplete for some cargo types', 'description' => 'Review parcel type rules to avoid underconfigured pricing behavior.', 'tone' => 'danger'] : null,
            ])),
            'quick_actions' => [
                ['label' => 'Review Verifications', 'route' => route('admin.verification.index')],
                ['label' => 'Manage Routes', 'route' => route('admin.routes.index')],
                ['label' => 'Tune Pricing', 'route' => route('admin.pricing.index')],
                ['label' => 'Check Invoices', 'route' => route('admin.invoices.index')],
            ],
            'recent_activity' => $this->recentActivity(),
            'verification_preview' => array_slice($this->adminDriverVerificationService->queue('pending'), 0, 4),
            'route_preview' => array_slice($this->routeManagementService->list(), 0, 4),
            'quotation_preview' => array_slice($this->adminQuotationService->list(), 0, 4),
            'invoice_preview' => array_slice($this->adminInvoiceService->list(), 0, 4),
        ];
    }

    public function requestsPayload(): array
    {
        return ParcelRequest::query()
            ->with(['customer:id,name,email', 'pickupLocation:id,name', 'dropoffLocation:id,name', 'packageType:id,name', 'assignedDriver.user:id,name', 'latestQuotation', 'latestInvoice', 'statusUpdates'])
            ->latest()
            ->get()
            ->map(fn (ParcelRequest $parcel) => [
                'id' => $parcel->id,
                'tracking_number' => $parcel->tracking_number,
                'status' => $parcel->status,
                'customer_name' => $parcel->customer?->name,
                'route' => ($parcel->pickupLocation?->name ?? 'Pickup') . ' -> ' . ($parcel->dropoffLocation?->name ?? 'Destination'),
                'package_type' => $parcel->packageType?->name,
                'assigned_driver' => $parcel->assignedDriver?->user?->name,
                'distance_km' => $parcel->distance_km,
                'pricing_summary' => [
                    'base_price' => $parcel->base_price,
                    'distance_fee' => $parcel->distance_fee,
                    'weight_surcharge' => $parcel->weight_surcharge,
                    'urgency_surcharge' => $parcel->urgency_surcharge,
                    'special_handling_fee' => $parcel->special_handling_fee,
                    'total_price' => $parcel->final_price ?: $parcel->total_price,
                ],
                'quotation' => $parcel->latestQuotation ? [
                    'id' => $parcel->latestQuotation->id,
                    'quotation_number' => $parcel->latestQuotation->quotation_number,
                    'status' => $parcel->latestQuotation->status,
                ] : null,
                'invoice' => $parcel->latestInvoice ? [
                    'id' => $parcel->latestInvoice->id,
                    'invoice_number' => $parcel->latestInvoice->invoice_number,
                    'status' => $parcel->latestInvoice->status,
                    'payment_status' => $parcel->latestInvoice->payment_status,
                ] : null,
                'timeline' => $parcel->statusUpdates->take(4)->map(fn ($update) => [
                    'id' => $update->id,
                    'status' => $update->status,
                    'title' => $update->title,
                    'message' => $update->message,
                    'time' => $update->created_at?->diffForHumans(),
                ])->values()->all(),
                'created_at' => optional($parcel->created_at)->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    private function recentActivity(): array
    {
        $items = collect();

        DriverLicence::query()->latest('submitted_at')->take(4)->get()->each(function (DriverLicence $licence) use ($items) {
            $items->push([
                'type' => 'verification',
                'title' => ($licence->driver?->user?->name ?? 'Driver') . ' submitted a licence',
                'meta' => $licence->licence_type_name . ' Â· ' . optional($licence->submitted_at)->diffForHumans(),
                'tone' => 'warning',
                'sort_ts' => optional($licence->submitted_at)->timestamp ?? 0,
            ]);
        });

        Invoice::query()->latest('issue_date')->take(4)->get()->each(function (Invoice $invoice) use ($items) {
            $items->push([
                'type' => 'invoice',
                'title' => 'Invoice ' . $invoice->invoice_number . ' issued',
                'meta' => 'Payment ' . str_replace('_', ' ', $invoice->payment_status) . ' Â· N$ ' . number_format((float) $invoice->total, 2),
                'tone' => $invoice->payment_status === 'paid' ? 'success' : 'warning',
                'sort_ts' => optional($invoice->updated_at)->timestamp ?? 0,
            ]);
        });

        Quotation::query()->latest('issue_date')->take(4)->get()->each(function (Quotation $quotation) use ($items) {
            $items->push([
                'type' => 'quotation',
                'title' => 'Quotation ' . $quotation->quotation_number . ' generated',
                'meta' => str_replace('_', ' ', $quotation->status) . ' Â· N$ ' . number_format((float) $quotation->total, 2),
                'tone' => 'brand',
                'sort_ts' => optional($quotation->updated_at)->timestamp ?? 0,
            ]);
        });

        ParcelRequest::query()->latest()->take(4)->get()->each(function (ParcelRequest $parcel) use ($items) {
            $items->push([
                'type' => 'request',
                'title' => 'Request ' . $parcel->tracking_number . ' updated',
                'meta' => str_replace('_', ' ', $parcel->status) . ' Â· ' . optional($parcel->created_at)->diffForHumans(),
                'tone' => 'neutral',
                'sort_ts' => optional($parcel->updated_at)->timestamp ?? 0,
            ]);
        });

        return $items->sortByDesc('sort_ts')->take(10)->map(function (array $item) {
            unset($item['sort_ts']);
            return $item;
        })->values()->all();
    }
}

