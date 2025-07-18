<?php
// SPDX-License-Identifier: BSD-3-Clause

declare(strict_types=1);

namespace Nbgrp\Tests\OneloginSamlBundle\Security\Http\Authenticator\Passport\Badge;

use Nbgrp\OneloginSamlBundle\Security\Http\Authenticator\Passport\Badge\DeferredEventBadge;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal
 */
#[CoversClass(DeferredEventBadge::class)]
final class DeferredEventBadgeTest extends TestCase
{
    public function testEmptyBadge(): void
    {
        $badge = new DeferredEventBadge();

        self::assertFalse($badge->isResolved());
        self::assertNull($badge->getEvent());
        self::assertTrue($badge->isResolved());
    }

    /**
     * @throws Exception
     */
    public function testEventBadge(): void
    {
        $badge = new DeferredEventBadge();

        self::assertFalse($badge->isResolved());

        $event = self::createStub(Event::class);
        $badge->setEvent($event);

        self::assertSame($event, $badge->getEvent());
        self::assertTrue($badge->isResolved());
    }
}
