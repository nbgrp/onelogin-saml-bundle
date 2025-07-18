<?php
// SPDX-License-Identifier: BSD-3-Clause

declare(strict_types=1);

namespace Nbgrp\Tests\OneloginSamlBundle\Security\User;

use Nbgrp\OneloginSamlBundle\Security\User\SamlUserInterface;
use Nbgrp\OneloginSamlBundle\Security\User\SamlUserProvider;
use Nbgrp\Tests\OneloginSamlBundle\TestUser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\InMemoryUser;

/**
 * @internal
 */
#[CoversClass(SamlUserProvider::class)]
final class SamlUserProviderTest extends TestCase
{
    public function testLoadUserByIdentifier(): void
    {
        $provider = new SamlUserProvider(TestUser::class, ['ROLE_USER']);
        $user = $provider->loadUserByIdentifier('tester');

        self::assertSame('tester', $user->getUserIdentifier());
        self::assertSame(['ROLE_USER'], $user->getRoles());
    }

    public function testRefreshUser(): void
    {
        $provider = new SamlUserProvider(TestUser::class, ['ROLE_USER']);
        $user = new TestUser('foo');

        self::assertSame($user, $provider->refreshUser($user));
    }

    public function testRefreshUnsupportedUser(): void
    {
        $provider = new SamlUserProvider(TestUser::class, ['ROLE_USER']);
        $user = new InMemoryUser('foo', 'pass');

        $this->expectException(UnsupportedUserException::class);
        $provider->refreshUser($user);
    }

    public function testSupportsClass(): void
    {
        $provider = new SamlUserProvider(TestUser::class, ['ROLE_USER']);
        self::assertTrue($provider->supportsClass(TestUser::class));
    }

    public function testSupportsSubclass(): void
    {
        $provider = new SamlUserProvider(SamlUserInterface::class, []);
        self::assertTrue($provider->supportsClass(TestUser::class));
    }

    public function testNotSupports(): void
    {
        $provider = new SamlUserProvider(TestUser::class, ['ROLE_USER']);
        self::assertFalse($provider->supportsClass(InMemoryUser::class));
    }

    public function testInvalidUserClass(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The $userClass argument should be a class implementing the Nbgrp\OneloginSamlBundle\Security\User\SamlUserInterface');
        /**
         * @psalm-suppress InvalidArgument
         * @phpstan-ignore-next-line
         */
        new SamlUserProvider(\stdClass::class, ['ROLE_ANY']);
    }
}
