<?php

namespace App\Services;

use Illuminate\Support\Arr;

class SmsTemplateService
{
    public function render(string $templateKey, array $context = []): string
    {
        $pickup = Arr::get($context, 'pickup', 'Pickup');
        $destination = Arr::get($context, 'destination', 'Destination');
        $tracking = Arr::get($context, 'tracking', 'Tracking unavailable');
        $driver = Arr::get($context, 'driver', 'your driver');

        return match ($templateKey) {
            'customer.driver_assigned' => "InterCity: Your parcel {$tracking} from {$pickup} to {$destination} has been assigned to {$driver}.",
            'customer.parcel_picked_up' => "InterCity: Your parcel {$tracking} has been picked up and is on the way.",
            'customer.parcel_in_transit' => "InterCity: Your parcel {$tracking} is in transit to {$destination}.",
            'customer.parcel_delivered' => "InterCity: Your parcel {$tracking} has been delivered successfully.",
            'driver.new_matching_request' => "InterCity: New delivery request {$pickup} to {$destination}. Check your app now.",
            'driver.urgent_job_alert' => "InterCity urgent: {$pickup} to {$destination} is ready now. Open the app to respond.",
            'driver.job_accepted' => "InterCity: You accepted job {$tracking}. Pickup at {$pickup}.",
            'driver.verification_approved' => "InterCity: Your driver verification is approved. You can now take delivery jobs.",
            'driver.verification_rejected' => "InterCity: Verification update needed. " . Arr::get($context, 'reason', 'Please review your licence details in the app.'),
            'customer.quotation_ready' => "InterCity: Your quotation " . Arr::get($context, 'quote_number', 'quotation') . " is ready.",
            'customer.invoice_ready' => "InterCity: Your invoice " . Arr::get($context, 'invoice_number', 'invoice') . " is ready.",
            'general.important_alert' => Arr::get($context, 'message', 'InterCity has an important update for you.'),
            default => Arr::get($context, 'message', 'InterCity update.'),
        };
    }
}
