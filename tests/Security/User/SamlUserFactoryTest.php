<?php
// SPDX-License-Identifier: BSD-3-Clause

declare(strict_types=1);

namespace Nbgrp\Tests\OneloginSamlBundle\Security\User;

use Nbgrp\OneloginSamlBundle\Security\User\SamlUserFactory;
use Nbgrp\Tests\OneloginSamlBundle\TestUser;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Nbgrp\OneloginSamlBundle\Security\User\SamlUserFactory
 *
 * @internal
 */
final class SamlUserFactoryTest extends TestCase
{
    public function testCreateUser(): void
    {
        $factory = new SamlUserFactory(TestUser::class, [
            'email' => '$email',
            'roles' => '$roles[]',
        ]);

        /** @var TestUser $user */
        $user = $factory->createUser('tester', [
            'email' => 'tester@example.com',
            'roles' => ['custom_role1', 'custom_role2'],
        ]);

        self::assertSame('tester', $user->getUserIdentifier());
        self::assertSame(['custom_role1', 'custom_role2'], $user->getRoles());
        self::assertSame('tester@example.com', $user->getEmail());
    }

    public function testCreateUserException(): void
    {
        $factory = new SamlUserFactory(TestUser::class, [
            'email' => '$email',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Attribute "email" not found in SAML data.');
        $factory->createUser('tester', []);
    }
}
