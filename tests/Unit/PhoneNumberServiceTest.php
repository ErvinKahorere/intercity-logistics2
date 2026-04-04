<?php

namespace Tests\Unit;

use App\Services\PhoneNumberService;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class PhoneNumberServiceTest extends TestCase
{
    public function test_it_normalizes_local_numbers_to_e164(): void
    {
        $service = new PhoneNumberService();

        $this->assertSame('+264812345678', $service->normalize('081 234 5678'));
        $this->assertSame('+264812345678', $service->normalize('00264812345678'));
    }

    public function test_it_rejects_invalid_phone_numbers(): void
    {
        $service = new PhoneNumberService();

        $this->expectException(\InvalidArgumentException::class);

        $service->normalize('12');
    }

    public function test_it_uses_sms_config_for_default_country_code(): void
    {
        Config::set('sms.default_country_code', '27');

        $service = new PhoneNumberService();

        $this->assertSame('+27821234567', $service->normalize('082 123 4567'));
    }
}
