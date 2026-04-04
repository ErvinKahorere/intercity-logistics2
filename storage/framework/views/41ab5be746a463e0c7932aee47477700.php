<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice <?php echo e($invoice->invoice_number); ?></title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #1f1f1f; font-size: 12px; margin: 28px; }
        .page { width: 100%; }
        .header { margin-bottom: 26px; }
        .brand-row { width: 100%; border-bottom: 1px solid #e7e7ea; padding-bottom: 18px; }
        .brand { font-size: 25px; font-weight: 800; color: #2f2e7c; letter-spacing: 0.04em; }
        .muted { color: #666a73; }
        .eyebrow { font-size: 10px; font-weight: 700; letter-spacing: 0.18em; text-transform: uppercase; color: #666a73; }
        .doc-title { font-size: 22px; font-weight: 800; color: #1f1f1f; margin-top: 6px; }
        .pill { display: inline-block; padding: 5px 11px; border-radius: 999px; background: #fff3c0; border: 1px solid #f2d766; color: #1f1f1f; font-size: 10px; font-weight: 800; letter-spacing: 0.14em; text-transform: uppercase; }
        .meta-grid, .summary-grid { width: 100%; margin-top: 18px; }
        .meta-grid td, .summary-grid td { vertical-align: top; padding: 0 10px 0 0; }
        .meta-card, .section-card { border: 1px solid #e7e7ea; border-radius: 16px; padding: 14px 16px; background: #fafafc; }
        .section-card { margin-top: 16px; }
        .card-label { font-size: 10px; font-weight: 700; letter-spacing: 0.16em; text-transform: uppercase; color: #666a73; }
        .card-value { margin-top: 8px; font-size: 14px; font-weight: 800; color: #1f1f1f; }
        .section-title { font-size: 12px; font-weight: 800; letter-spacing: 0.12em; text-transform: uppercase; color: #2f2e7c; margin-bottom: 12px; }
        .detail-table { width: 100%; border-collapse: collapse; }
        .detail-table td { padding: 8px 0; border-bottom: 1px solid #ececf0; }
        .detail-table td:last-child { text-align: right; font-weight: 700; }
        .detail-table tr:last-child td { border-bottom: 0; }
        .breakdown-table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        .breakdown-table td { padding: 10px 0; border-bottom: 1px solid #ececf0; }
        .breakdown-table td:last-child { text-align: right; font-weight: 700; }
        .breakdown-table tr:last-child td { border-bottom: 0; }
        .total-box { margin-top: 14px; border: 1px solid #d8dbf7; border-radius: 16px; padding: 14px 16px; background: #f6f7ff; }
        .total-row { width: 100%; }
        .total-row td:last-child { text-align: right; font-size: 22px; font-weight: 800; color: #2f2e7c; }
        .footer-note { margin-top: 16px; font-size: 11px; line-height: 1.65; color: #666a73; }
    </style>
</head>
<body>
    <div class="page">
        <div class="header">
            <table class="brand-row">
                <tr>
                    <td>
                        <div class="brand">InterCity Logistics</div>
                        <div class="doc-title">Invoice</div>
                        <div class="muted" style="margin-top: 6px;">Commercial billing record for a confirmed booking.</div>
                    </td>
                    <td style="text-align: right;">
                        <span class="pill"><?php echo e(strtoupper((string) $invoice->status)); ?> / <?php echo e(strtoupper((string) $invoice->payment_status)); ?></span>
                    </td>
                </tr>
            </table>

            <table class="meta-grid">
                <tr>
                    <td width="33.33%">
                        <div class="meta-card">
                            <div class="card-label">Invoice Ref</div>
                            <div class="card-value"><?php echo e($invoice->invoice_number); ?></div>
                        </div>
                    </td>
                    <td width="33.33%">
                        <div class="meta-card">
                            <div class="card-label">Issue Date</div>
                            <div class="card-value"><?php echo e(optional($invoice->issue_date)->format('d M Y')); ?></div>
                        </div>
                    </td>
                    <td width="33.33%">
                        <div class="meta-card">
                            <div class="card-label">Due Date</div>
                            <div class="card-value"><?php echo e(optional($invoice->due_date)->format('d M Y')); ?></div>
                        </div>
                    </td>
                </tr>
            </table>
        </div>

        <table class="summary-grid">
            <tr>
                <td width="48%">
                    <div class="section-card">
                        <div class="section-title">Customer Details</div>
                        <div class="card-value" style="margin-top: 0;"><?php echo e(data_get($invoice->customer_snapshot, 'name', 'Customer')); ?></div>
                        <div class="muted" style="margin-top: 8px;"><?php echo e(data_get($invoice->customer_snapshot, 'email', 'No email recorded')); ?></div>
                        <div class="muted" style="margin-top: 4px;"><?php echo e(data_get($invoice->customer_snapshot, 'phone', 'No phone recorded')); ?></div>
                    </div>
                </td>
                <td width="4%"></td>
                <td width="48%">
                    <div class="section-card">
                        <div class="section-title">Booking References</div>
                        <div class="card-value" style="margin-top: 0;"><?php echo e($invoice->tracking_number); ?></div>
                        <div class="muted" style="margin-top: 8px;">Booking <?php echo e($invoice->booking_reference); ?></div>
                        <div class="muted" style="margin-top: 4px;">Driver <?php echo e(data_get($invoice->driver_snapshot, 'name', 'Pending assignment')); ?></div>
                    </div>
                </td>
            </tr>
        </table>

        <?php if($invoice->driver_snapshot): ?>
            <div class="section-card">
                <div class="section-title">Driver Details</div>
                <table class="detail-table">
                    <tr>
                        <td class="muted">Driver</td>
                        <td><?php echo e(data_get($invoice->driver_snapshot, 'name', 'Assigned driver')); ?></td>
                    </tr>
                    <tr>
                        <td class="muted">Phone</td>
                        <td><?php echo e(data_get($invoice->driver_snapshot, 'phone', 'No phone recorded')); ?></td>
                    </tr>
                    <tr>
                        <td class="muted">Verification</td>
                        <td><?php echo e(ucfirst(str_replace('_', ' ', (string) data_get($invoice->driver_snapshot, 'verification_status', 'pending')))); ?></td>
                    </tr>
                    <tr>
                        <td class="muted">Vehicle</td>
                        <td><?php echo e(data_get($invoice->driver_snapshot, 'vehicle_label', 'Vehicle on file')); ?></td>
                    </tr>
                </table>
            </div>
        <?php endif; ?>

        <div class="section-card">
            <div class="section-title">Route & Parcel</div>
            <table class="detail-table">
                <tr>
                    <td class="muted">Pickup</td>
                    <td><?php echo e(data_get($invoice->route_snapshot, 'pickup_city', 'Pickup')); ?></td>
                </tr>
                <tr>
                    <td class="muted">Destination</td>
                    <td><?php echo e(data_get($invoice->route_snapshot, 'dropoff_city', 'Destination')); ?></td>
                </tr>
                <tr>
                    <td class="muted">Receiver</td>
                    <td><?php echo e(data_get($invoice->route_snapshot, 'receiver_name', 'Receiver')); ?> / <?php echo e(data_get($invoice->route_snapshot, 'receiver_phone', 'No phone')); ?></td>
                </tr>
                <tr>
                    <td class="muted">Weight</td>
                    <td><?php echo e(number_format((float) data_get($invoice->route_snapshot, 'weight_kg', 0), 2)); ?> kg</td>
                </tr>
                <tr>
                    <td class="muted">Urgency</td>
                    <td><?php echo e(ucfirst(str_replace('_', ' ', (string) data_get($invoice->route_snapshot, 'urgency_level', 'standard')))); ?></td>
                </tr>
            </table>
        </div>

        <div class="section-card">
            <div class="section-title">Pricing Breakdown</div>
            <table class="breakdown-table">
                <tr><td class="muted">Base fee</td><td>N$ <?php echo e(number_format((float) $invoice->base_fee, 2)); ?></td></tr>
                <tr><td class="muted">Distance fee</td><td>N$ <?php echo e(number_format((float) $invoice->distance_fee, 2)); ?></td></tr>
                <tr><td class="muted">Weight fee</td><td>N$ <?php echo e(number_format((float) $invoice->weight_fee, 2)); ?></td></tr>
                <tr><td class="muted">Urgency fee</td><td>N$ <?php echo e(number_format((float) $invoice->urgency_fee, 2)); ?></td></tr>
                <tr><td class="muted">Special handling</td><td>N$ <?php echo e(number_format((float) $invoice->special_handling_fee, 2)); ?></td></tr>
            </table>

            <div class="total-box">
                <table class="total-row">
                    <tr>
                        <td>
                            <div class="card-label">Amount Due</div>
                            <div class="muted" style="margin-top: 6px;">Payment status: <?php echo e(ucfirst(str_replace('_', ' ', (string) $invoice->payment_status))); ?></div>
                        </td>
                        <td>N$ <?php echo e(number_format((float) $invoice->total, 2)); ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <?php if($invoice->notes): ?>
            <div class="section-card">
                <div class="section-title">Notes</div>
                <div class="muted" style="line-height: 1.7;"><?php echo e($invoice->notes); ?></div>
            </div>
        <?php endif; ?>

        <div class="footer-note">
            This invoice is generated from the confirmed booking and quotation record held by InterCity Logistics at the time of issue.
        </div>
    </div>
</body>
</html>
<?php /**PATH E:\src\xamp\htdocs\InterCity_upgraded\resources\views/pdf/invoice.blade.php ENDPATH**/ ?>