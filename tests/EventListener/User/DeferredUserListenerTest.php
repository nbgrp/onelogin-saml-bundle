<?php
// SPDX-License-Identifier: BSD-3-Clause

declare(strict_types=1);

namespace Nbgrp\Tests\OneloginSamlBundle\EventListener\User;

use Nbgrp\OneloginSamlBundle\Event\AbstractUserEvent;
use Nbgrp\OneloginSamlBundle\EventListener\User\DeferredUserListener;
use Nbgrp\OneloginSamlBundle\Security\Http\Authenticator\Passport\Badge\DeferredEventBadge;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @covers \Nbgrp\OneloginSamlBundle\EventListener\User\DeferredUserListener
 *
 * @internal
 */
final class DeferredUserListenerTest extends TestCase
{
    public function testWithoutBadge(): void
    {
        $event = new CheckPassportEvent(
            $this->createStub(AuthenticatorInterface::class),
            new SelfValidatingPassport(new UserBadge('tester')),
        );

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher
            ->expects(self::never())
            ->method('dispatch')
        ;

        (new DeferredUserListener())->dispatchDeferredEvent($event, '', $eventDispatcher);
    }

    public function testBadgeWithoutEvent(): void
    {
        $event = new CheckPassportEvent(
            $this->createStub(AuthenticatorInterface::class),
            new SelfValidatingPassport(new UserBadge('tester'), [new DeferredEventBadge()]),
        );

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher
            ->expects(self::never())
            ->method('dispatch')
        ;

        (new DeferredUserListener())->dispatchDeferredEvent($event, '', $eventDispatcher);
    }

    public function testSuccessfulEventDispatching(): void
    {
        $deferredEvent = $this->createMock(AbstractUserEvent::class);

        $deferredEventBadge = new DeferredEventBadge();
        $deferredEventBadge->setEvent($deferredEvent);

        $event = new CheckPassportEvent(
            $this->createStub(AuthenticatorInterface::class),
            new SelfValidatingPassport(new UserBadge('tester'), [$deferredEventBadge]),
        );

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with($deferredEvent)
        ;

        (new DeferredUserListener())->dispatchDeferredEvent($event, '', $eventDispatcher);
    }
}
