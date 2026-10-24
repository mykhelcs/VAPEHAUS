<?php

declare(strict_types=1);

namespace Vapehaus\Tests\Unit\Domain;

use DateTimeImmutable;
use Vapehaus\Domain\Exception\UnderageException;
use Vapehaus\Domain\Model\User;
use Vapehaus\Domain\ValueObject\Email;
use Vapehaus\Domain\ValueObject\Role;
use Vapehaus\Tests\TestCase;

final class UserTest extends TestCase
{
    public function testAdultUserCanBeVerified(): void
    {
        $dob = (new DateTimeImmutable('-22 years'))->format('Y-m-d');
        $user = new User(
            'usr-1',
            new Email('vaper@vapehaus.test'),
            'hashed_secret',
            'Alex',
            'Rios',
            '+639171234567',
            Role::customer(),
            $dob,
            false
        );

        $this->assertEquals(22, $user->calculateAge());
        $user->markAgeVerified(18);
        $this->assertTrue($user->isAgeVerified());
    }

    public function testMinorThrowsUnderageException(): void
    {
        $dob = (new DateTimeImmutable('-16 years'))->format('Y-m-d');
        $user = new User(
            'usr-2',
            new Email('minor@test.com'),
            'secret',
            'Kid',
            'Vaper',
            null,
            Role::customer(),
            $dob,
            false
        );

        $this->expectException(UnderageException::class);
        $user->markAgeVerified(18);
    }

    public function testAdminRoleCheck(): void
    {
        $admin = new User(
            'adm-1',
            new Email('admin@vapehaus.test'),
            'secret',
            'Master',
            'Admin',
            null,
            Role::admin()
        );

        $this->assertTrue($admin->isAdmin());
        $this->assertEquals('admin', $admin->getRole()->getValue());
    }
}
