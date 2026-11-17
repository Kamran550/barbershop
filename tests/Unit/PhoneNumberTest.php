<?php

namespace Tests\Unit;

use App\Support\PhoneNumber;
use PHPUnit\Framework\TestCase;

class PhoneNumberTest extends TestCase
{
    public function test_various_formats_normalize_to_e164(): void
    {
        foreach (['050 123 45 67', '+994 50 123-45-67', '994501234567', '(050) 123 45 67', '501234567'] as $input) {
            $this->assertSame('+994501234567', PhoneNumber::normalize($input), $input);
        }
    }

    public function test_invalid_numbers_are_rejected(): void
    {
        foreach (['', '12345', '+7 900 123 45 67', '040 123 45 67', '+994 50 123 45', 'abc'] as $input) {
            $this->assertNull(PhoneNumber::normalize($input), $input);
        }
    }

    public function test_search_fragment_drops_local_leading_zero(): void
    {
        $this->assertSame('50123', PhoneNumber::searchFragment('050 123'));
        $this->assertSame('994501', PhoneNumber::searchFragment('+994 501'));
        $this->assertNull(PhoneNumber::searchFragment('05'));
        $this->assertNull(PhoneNumber::searchFragment('Kamal'));
    }

    public function test_format_and_mask(): void
    {
        $this->assertSame('+994 50 123 45 67', PhoneNumber::format('+994501234567'));
        $this->assertSame('+994 50 *** ** 67', PhoneNumber::mask('+994501234567'));
    }
}
