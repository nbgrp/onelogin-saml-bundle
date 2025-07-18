<?php
// SPDX-License-Identifier: BSD-3-Clause

declare(strict_types=1);

namespace Nbgrp\Tests\OneloginSamlBundle\Onelogin;

use Nbgrp\OneloginSamlBundle\Onelogin\AuthRegistry;
use Nbgrp\OneloginSamlBundle\Onelogin\AuthRegistryInterface;
use OneLogin\Saml2\Auth;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(AuthRegistry::class)]
final class AuthRegistryTest extends TestCase
{
    private AuthRegistryInterface $registry;

    /**
     * @throws Exception
     */
    public function testRegistry(): void
    {
        $defaultAuth = self::createStub(Auth::class);
        $this->registry->addService('default', 'default', $defaultAuth);

        $defaultAdditionalAuth = self::createStub(Auth::class);
        $this->registry->addService('default', 'additional', $defaultAdditionalAuth);

        $additionalAuth = self::createStub(Auth::class);
        $this->registry->addService('additional', 'default', $additionalAuth);

        self::assertTrue($this->registry->hasService('default', 'default'));
        self::assertTrue($this->registry->hasService('additional', 'default'));
        self::assertFalse($this->registry->hasService('undefined', 'default'));

        self::assertSame($this->registry->getService('default', 'default'), $defaultAuth);
        self::assertSame($this->registry->getService('default', 'additional'), $defaultAdditionalAuth);
        self::assertSame($this->registry->getService('additional', 'default'), $additionalAuth);
        self::assertSame($this->registry->getDefaultService(), $defaultAuth);
    }

    public function testGetNotExistsServiceException(): void
    {
        $this->expectException(\OutOfBoundsException::class);
        $this->expectExceptionMessage('Auth service for keys "undefined undefined" does not exists.');
        $this->registry->getService('undefined', 'undefined');
    }

    /**
     * @throws Exception
     */
    public function testAddExistenceServiceException(): void
    {
        $defaultAuth = self::createStub(Auth::class);
        $this->registry->addService('default', 'default', $defaultAuth);

        $this->expectException(\OverflowException::class);
        $this->expectExceptionMessage('Auth service with key "default" already exists.');
        $this->registry->addService('default', 'default', $defaultAuth);
    }

    public function testEmptyRegistryDefaultService(): void
    {
        $this->expectException(\UnderflowException::class);
        $this->expectExceptionMessage('There is no configured Auth services.');

        $this->registry->getDefaultService();
    }

    protected function setUp(): void
    {
        $this->registry = new AuthRegistry();
    }
}
