<?php declare(strict_types=1);
// SPDX-License-Identifier: BSD-3-Clause

namespace Nbgrp\Tests\OneloginSamlBundle\Security\User;

use Nbgrp\OneloginSamlBundle\Security\User\SamlUserProvider;
use Nbgrp\Tests\OneloginSamlBundle\TestUser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @covers \Nbgrp\OneloginSamlBundle\Security\User\SamlUserProvider
 *
 * @internal
 */
final class SamlUserProviderTest extends TestCase
{
    private SamlUserProvider $provider;

    public function testLoadUserByIdentifier(): void
    {
        $user = $this->provider->loadUserByIdentifier('tester');

        self::assertSame('tester', $user->getUserIdentifier());
        self::assertSame(['ROLE_DEFAULT_USER'], $user->getRoles());
    }

    public function testRefreshUser(): void
    {
        $user = new TestUser('foo');
        self::assertSame($user, $this->provider->refreshUser($user));
    }

    public function testRefreshUnsupportedUser(): void
    {
        $user = new InMemoryUser('foo', 'pass');

        $this->expectException(UnsupportedUserException::class);
        $this->provider->refreshUser($user);
    }

    public function testSupportsClass(): void
    {
        self::assertTrue($this->provider->supportsClass(TestUser::class));
    }

    public function testSupportsSubclass(): void
    {
        $provider = new SamlUserProvider(UserInterface::class, []);
        self::assertTrue($provider->supportsClass(TestUser::class));
    }

    protected function setUp(): void
    {
        $this->provider = new SamlUserProvider(TestUser::class, ['ROLE_DEFAULT_USER']);
    }
}
