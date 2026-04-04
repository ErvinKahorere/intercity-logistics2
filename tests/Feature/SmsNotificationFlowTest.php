<?php

namespace Tests\Feature;

use App\Jobs\SendSmsJob;
use App\Models\Driver;
use App\Models\DriverLicence;
use App\Models\Invoice;
use App\Models\Location;
use App\Models\PackageType;
use App\Models\ParcelRequest;
use App\Models\Quotation;
use App\Models\SmsNotificationLog;
use App\Models\User;
use App\Services\DriverMatchService;
use App\Services\DriverVerificationService;
use App\Services\InvoiceService;
use App\Services\ParcelWorkflowService;
use App\Services\PricingService;
use App\Services\QuotationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Config;
use Mockery;
use Tests\TestCase;

class SmsNotificationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_sms_service_queues_and_suppresses_duplicate_messages(): void
    {
        Queue::fake();

        $user = $this->createUser([
            'phone' => '0812345678',
        ]);

        $log = app(\App\Services\SmsService::class)->queueTemplate(
            $user,
            'general.important_alert',
            ['message' => 'InterCity test alert'],
            [
                'event_type' => 'important_alert',
                'preference_key' => 'important_alerts',
            ]
        );

        $this->assertNotNull($log);
        $this->assertDatabaseHas('sms_notification_logs', [
            'id' => $log->id,
            'status' => 'queued',
            'normalized_phone' => '+264812345678',
            'event_type' => 'important_alert',
        ]);
        Queue::assertPushed(SendSmsJob::class, 1);

        $skipped = app(\App\Services\SmsService::class)->queueTemplate(
            $user,
            'general.important_alert',
            ['message' => 'InterCity test alert'],
            [
                'event_type' => 'important_alert',
                'preference_key' => 'important_alerts',
            ]
        );

        $this->assertSame('skipped', $skipped?->status);
        Queue::assertPushed(SendSmsJob::class, 1);
    }

    public function test_queue_message_respects_user_sms_preferences(): void
    {
        Queue::fake();

        $user = $this->createUser([
            'phone' => '0812345678',
            'sms_notification_preferences' => [
                'billing_updates' => false,
            ],
        ]);

        $log = app(\App\Services\SmsService::class)->queueMessage(
            $user,
            'Your invoice is ready.',
            [
                'event_type' => 'customer_invoice_ready',
                'preference_key' => 'billing_updates',
            ]
        );

        $this->assertSame('skipped', $log?->status);
        $this->assertSame('preference_disabled', $log?->meta['reason'] ?? null);
        Queue::assertNothingPushed();
    }

    public function test_unknown_sms_provider_falls_back_to_log_provider(): void
    {
        Queue::fake();

        Config::set('sms.default', 'mystery');
        app()->forgetInstance(\App\Contracts\SmsProviderInterface::class);

        $user = $this->createUser(['phone' => '0812345678']);

        $log = app(\App\Services\SmsService::class)->queueMessage(
            $user,
            'Fallback notification',
            ['event_type' => 'important_alert']
        );

        $this->assertSame('queued', $log?->status);
        $this->assertSame('log', $log?->provider);
        Queue::assertPushed(SendSmsJob::class, 1);
    }

    public function test_twilio_provider_unavailability_skips_queue_cleanly(): void
    {
        Queue::fake();

        Config::set('sms.default', 'twilio');
        Config::set('sms.providers.twilio.enabled', true);
        Config::set('sms.providers.twilio.sid', null);
        Config::set('sms.providers.twilio.token', null);
        Config::set('sms.providers.twilio.from', null);
        app()->forgetInstance(\App\Contracts\SmsProviderInterface::class);

        $user = $this->createUser(['phone' => '0812345678']);

        $log = app(\App\Services\SmsService::class)->queueMessage(
            $user,
            'Trial notification',
            ['event_type' => 'important_alert']
        );

        $this->assertSame('skipped', $log?->status);
        $this->assertSame('provider_unavailable', $log?->meta['reason'] ?? null);
        $this->assertSame('twilio', $log?->provider);
        Queue::assertNothingPushed();
    }

    public function test_verification_review_queues_sms_update(): void
    {
        Queue::fake();

        $reviewer = $this->createUser(['role' => 'admin', 'phone' => '0810000001']);
        $driverUser = $this->createUser(['role' => 'Driver', 'phone' => '0810000002']);
        $driver = Driver::create([
            'user_id' => $driverUser->id,
            'phone' => $driverUser->phone,
            'status' => 'active',
            'verification_status' => 'pending',
        ]);

        $licence = DriverLicence::create([
            'driver_id' => $driver->id,
            'licence_type_code' => 'C',
            'licence_type_name' => 'Code C',
            'expiry_date' => now()->addYear(),
            'document_path' => 'driver-licences/test.pdf',
            'verification_status' => 'pending',
            'submitted_at' => now(),
            'is_primary' => true,
        ]);

        app(DriverVerificationService::class)->review($licence, 'verified', $reviewer);

        $this->assertDatabaseHas('sms_notification_logs', [
            'user_id' => $driverUser->id,
            'event_type' => 'driver_verification_verified',
            'status' => 'queued',
        ]);
        Queue::assertPushed(SendSmsJob::class);
    }

    public function test_parcel_acceptance_and_tracking_updates_queue_sms_notifications(): void
    {
        Queue::fake();

        $customer = $this->createUser(['phone' => '0810000003']);
        $driverUser = $this->createUser(['role' => 'Driver', 'phone' => '0810000004']);
        $driver = Driver::create([
            'user_id' => $driverUser->id,
            'phone' => $driverUser->phone,
            'status' => 'active',
            'verification_status' => 'verified',
        ]);

        $pickup = Location::create(['name' => 'Windhoek']);
        $dropoff = Location::create(['name' => 'Walvis Bay']);
        $packageType = PackageType::create(['name' => 'Documents']);

        $parcel = ParcelRequest::create([
            'user_id' => $customer->id,
            'tracking_number' => 'ICL-TEST-001',
            'pickup_location_id' => $pickup->id,
            'dropoff_location_id' => $dropoff->id,
            'package_type_id' => $packageType->id,
            'receiver_name' => 'Receiver',
            'receiver_phone' => '0819999999',
            'load_size' => 'small',
            'urgency_level' => 'express',
            'status' => ParcelRequest::STATUS_MATCHED,
            'matched_driver_ids' => [$driver->id],
            'total_price' => 900,
            'final_price' => 900,
        ]);

        $invoiceService = Mockery::mock(InvoiceService::class);
        $invoiceService->shouldReceive('createForParcelRequest')->once()->andReturn(Mockery::mock(Invoice::class));

        $service = new ParcelWorkflowService(
            Mockery::mock(DriverMatchService::class),
            Mockery::mock(PricingService::class),
            Mockery::mock(QuotationService::class),
            $invoiceService,
            app(\App\Services\SmsService::class),
        );

        $parcel = $service->acceptByDriver($parcel->loadMissing(['customer', 'pickupLocation', 'dropoffLocation', 'packageType']), $driver);
        $parcel = $service->transitionByDriver($parcel->fresh(['customer', 'pickupLocation', 'dropoffLocation', 'packageType']), $driver, ParcelRequest::STATUS_PICKED_UP);
        $parcel = $service->transitionByDriver($parcel->fresh(['customer', 'pickupLocation', 'dropoffLocation', 'packageType']), $driver, ParcelRequest::STATUS_IN_TRANSIT);
        $parcel = $service->transitionByDriver($parcel->fresh(['customer', 'pickupLocation', 'dropoffLocation', 'packageType']), $driver, ParcelRequest::STATUS_ARRIVED);
        $parcel = $service->transitionByDriver($parcel->fresh(['customer', 'pickupLocation', 'dropoffLocation', 'packageType']), $driver, ParcelRequest::STATUS_DELIVERED);

        $this->assertDatabaseHas('sms_notification_logs', [
            'user_id' => $customer->id,
            'event_type' => 'customer_driver_assigned',
        ]);
        $this->assertDatabaseHas('sms_notification_logs', [
            'user_id' => $driverUser->id,
            'event_type' => 'driver_job_accepted',
        ]);
        $this->assertDatabaseHas('sms_notification_logs', [
            'user_id' => $customer->id,
            'event_type' => 'customer_picked_up',
        ]);
        $this->assertDatabaseHas('sms_notification_logs', [
            'user_id' => $customer->id,
            'event_type' => 'customer_in_transit',
        ]);
        $this->assertDatabaseHas('sms_notification_logs', [
            'user_id' => $customer->id,
            'event_type' => 'customer_delivered',
        ]);
    }

    public function test_quotation_and_invoice_ready_queue_sms_notifications(): void
    {
        Queue::fake();

        $customer = $this->createUser(['phone' => '0810000005']);
        $pickup = Location::create(['name' => 'Swakopmund']);
        $dropoff = Location::create(['name' => 'Windhoek']);
        $packageType = PackageType::create(['name' => 'Small Parcel']);

        $pricingService = Mockery::mock(PricingService::class);
        $pricingService->shouldReceive('quote')->once()->andReturn([
            'distance_km' => 350,
            'estimated_hours' => 4.5,
            'base_price' => 120,
            'distance_fee' => 300,
            'weight_surcharge' => 20,
            'urgency_surcharge' => 45,
            'special_handling_fee' => 0,
            'minimum_charge' => 0,
            'total_price' => 485,
            'pricing_breakdown' => [],
        ]);

        $invoiceService = new InvoiceService(app(\App\Services\SmsService::class));
        $quotationService = new QuotationService($pricingService, $invoiceService, app(\App\Services\SmsService::class));

        $quotation = $quotationService->createFromPreview($customer, [
            'pickup_location_id' => $pickup->id,
            'dropoff_location_id' => $dropoff->id,
            'package_type_id' => $packageType->id,
            'urgency_level' => 'standard',
            'load_size' => 'small',
            'weight_kg' => 3,
        ]);

        $parcel = ParcelRequest::create([
            'user_id' => $customer->id,
            'tracking_number' => 'ICL-TEST-002',
            'pickup_location_id' => $pickup->id,
            'dropoff_location_id' => $dropoff->id,
            'package_type_id' => $packageType->id,
            'receiver_name' => 'Receiver',
            'receiver_phone' => '0811231234',
            'load_size' => 'small',
            'urgency_level' => 'standard',
            'status' => ParcelRequest::STATUS_ACCEPTED,
            'total_price' => 485,
            'final_price' => 485,
            'base_price' => 120,
            'distance_fee' => 300,
            'weight_surcharge' => 20,
            'urgency_surcharge' => 45,
            'special_handling_fee' => 0,
        ]);

        $invoiceService->createForParcelRequest($parcel->loadMissing(['customer', 'pickupLocation', 'dropoffLocation', 'packageType']), $quotation);

        $this->assertDatabaseHas('sms_notification_logs', [
            'user_id' => $customer->id,
            'event_type' => 'customer_quotation_ready',
        ]);
        $this->assertDatabaseHas('sms_notification_logs', [
            'user_id' => $customer->id,
            'event_type' => 'customer_invoice_ready',
        ]);
    }

    public function test_twilio_status_callback_updates_sms_log_status(): void
    {
        Config::set('sms.providers.twilio.token', 'trial-token');
        Config::set('sms.providers.twilio.validate_signature', true);

        $log = SmsNotificationLog::create([
            'provider' => 'twilio',
            'event_type' => 'customer_driver_assigned',
            'recipient_phone' => '+264811111111',
            'normalized_phone' => '+264811111111',
            'message' => 'Test message',
            'status' => 'queued',
            'provider_message_id' => 'SM123456',
        ]);

        $payload = [
            'MessageSid' => 'SM123456',
            'MessageStatus' => 'delivered',
        ];
        $url = 'http://localhost/webhooks/twilio/sms-status';
        $signature = base64_encode(hash_hmac('sha1', $url . 'MessageSidSM123456MessageStatusdelivered', 'trial-token', true));

        $response = $this->post($url, $payload, [
            'X-Twilio-Signature' => $signature,
        ]);

        $response->assertNoContent();

        $this->assertDatabaseHas('sms_notification_logs', [
            'id' => $log->id,
            'status' => 'delivered',
        ]);
    }

    public function test_twilio_callback_can_upgrade_failed_message_to_delivered(): void
    {
        Config::set('sms.providers.twilio.token', 'trial-token');
        Config::set('sms.providers.twilio.validate_signature', true);
        Config::set('sms.providers.twilio.status_callback', 'https://intercity.test/webhooks/twilio/sms-status');

        $log = SmsNotificationLog::create([
            'provider' => 'twilio',
            'event_type' => 'customer_driver_assigned',
            'recipient_phone' => '+264811111111',
            'normalized_phone' => '+264811111111',
            'message' => 'Test message',
            'status' => 'failed',
            'provider_message_id' => 'SM777777',
            'failed_at' => now(),
        ]);

        $payload = [
            'MessageSid' => 'SM777777',
            'MessageStatus' => 'delivered',
        ];
        $url = 'https://intercity.test/webhooks/twilio/sms-status';
        $signature = base64_encode(hash_hmac('sha1', $url . 'MessageSidSM777777MessageStatusdelivered', 'trial-token', true));

        $response = $this->post('http://localhost/webhooks/twilio/sms-status', $payload, [
            'X-Twilio-Signature' => $signature,
        ]);

        $response->assertNoContent();

        $this->assertDatabaseHas('sms_notification_logs', [
            'id' => $log->id,
            'status' => 'delivered',
        ]);
    }

    public function test_twilio_callback_does_not_downgrade_a_delivered_message(): void
    {
        Config::set('sms.providers.twilio.token', 'trial-token');
        Config::set('sms.providers.twilio.validate_signature', true);
        Config::set('sms.providers.twilio.status_callback', 'https://intercity.test/webhooks/twilio/sms-status');

        $log = SmsNotificationLog::create([
            'provider' => 'twilio',
            'event_type' => 'customer_driver_assigned',
            'recipient_phone' => '+264811111111',
            'normalized_phone' => '+264811111111',
            'message' => 'Test message',
            'status' => 'delivered',
            'provider_message_id' => 'SM654321',
            'sent_at' => now(),
        ]);

        $payload = [
            'MessageSid' => 'SM654321',
            'MessageStatus' => 'queued',
        ];
        $url = 'https://intercity.test/webhooks/twilio/sms-status';
        $signature = base64_encode(hash_hmac('sha1', $url . 'MessageSidSM654321MessageStatusqueued', 'trial-token', true));

        $response = $this->post('http://localhost/webhooks/twilio/sms-status', $payload, [
            'X-Twilio-Signature' => $signature,
        ]);

        $response->assertNoContent();

        $this->assertDatabaseHas('sms_notification_logs', [
            'id' => $log->id,
            'status' => 'delivered',
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function createUser(array $overrides = []): User
    {
        return User::create(array_merge([
            'name' => 'Test User',
            'email' => uniqid('user_', true) . '@example.com',
            'password' => bcrypt('password'),
            'role' => 'user',
            'phone' => '0810000000',
            'sms_notifications_enabled' => true,
            'email_verified_at' => now(),
        ], $overrides));
    }
}
