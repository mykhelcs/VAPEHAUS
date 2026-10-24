<?php

declare(strict_types=1);

namespace Vapehaus\Tests\Unit\Domain;

use Vapehaus\Domain\Model\UserAddress;
use Vapehaus\Tests\TestCase;

final class UserAddressTest extends TestCase
{
    public function testFormattedAddressString(): void
    {
        $address = new UserAddress(
            'adr_1',
            'usr_1',
            'Office',
            'Juan Dela Cruz',
            '+639171112222',
            'Unit 501, Vapehaus HQ',
            'BGC',
            'Taguig City',
            'Metro Manila',
            '1634',
            true
        );

        $this->assertEquals(
            'Juan Dela Cruz (+639171112222) - Unit 501, Vapehaus HQ, BGC, Taguig City, Metro Manila, 1634',
            $address->getFormattedAddress()
        );
        $this->assertTrue($address->isDefault());
    }
}
