<?php declare(strict_types=1);
// SPDX-License-Identifier: BSD-3-Clause

namespace Nbgrp\Tests\OneloginSamlBundle\Security\Http\Authenticator\Passport\Badge;

use Nbgrp\OneloginSamlBundle\Security\Http\Authenticator\Passport\Badge\DeferredEventBadge;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @covers \Nbgrp\OneloginSamlBundle\Security\Http\Authenticator\Passport\Badge\DeferredEventBadge
 *
 * @internal
 */
final class DeferredEventBadgeTest extends TestCase
{
    public function testEmptyBadge(): void
    {
        $badge = new DeferredEventBadge();

        self::assertFalse($badge->isResolved());
        self::assertNull($badge->getEvent());
        self::assertTrue($badge->isResolved());
    }

    public function testEventBadge(): void
    {
        $badge = new DeferredEventBadge();

        self::assertFalse($badge->isResolved());

        $event = $this->createStub(Event::class);
        $badge->setEvent($event);

        self::assertSame($event, $badge->getEvent());
        self::assertTrue($badge->isResolved());
    }
}
