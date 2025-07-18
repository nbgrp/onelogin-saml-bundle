<?php
// SPDX-License-Identifier: BSD-3-Clause

declare(strict_types=1);

namespace Nbgrp\Tests\OneloginSamlBundle\EventListener\User;

use Doctrine\ORM\EntityManagerInterface;
use Nbgrp\OneloginSamlBundle\Event\AbstractUserEvent;
use Nbgrp\OneloginSamlBundle\Event\UserCreatedEvent;
use Nbgrp\OneloginSamlBundle\Event\UserModifiedEvent;
use Nbgrp\OneloginSamlBundle\EventListener\User\AbstractUserListener;
use Nbgrp\OneloginSamlBundle\EventListener\User\UserCreatedListener;
use Nbgrp\OneloginSamlBundle\EventListener\User\UserModifiedListener;
use Nbgrp\Tests\OneloginSamlBundle\TestUser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Exception as MockObjectException;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(UserModifiedListener::class)]
#[CoversClass(UserCreatedListener::class)]
#[CoversClass(AbstractUserListener::class)]
#[CoversClass(AbstractUserEvent::class)]
final class UserListenersTest extends TestCase
{
    /**
     * @param class-string $listenerClass
     * @param class-string $eventClass
     */
    #[DataProvider('provideUserListenersCases')]
    public function testUserListeners(
        string $listenerClass,
        string $eventClass,
        bool $needPersist,
    ): void {
        $user = new TestUser('tester');

        try {
            $entityManager = $this->createMock(EntityManagerInterface::class);

            if ($needPersist) {
                $entityManager->expects(self::once())->method('persist')->with($user);
                $entityManager->expects(self::once())->method('flush');
            } else {
                $entityManager->expects(self::never())->method('persist');
                $entityManager->expects(self::never())->method('flush');
            }
        } catch (MockObjectException $e) {
            self::fail(\sprintf('Failed to create mock for EntityManagerInterface: %s', $e->getMessage()));
        }

        $listener = match ($listenerClass) {
            UserCreatedListener::class => new UserCreatedListener($entityManager, $needPersist),
            UserModifiedListener::class => new UserModifiedListener($entityManager, $needPersist),
            default => throw new \InvalidArgumentException("Unsupported listener class: {$listenerClass}"),
        };

        $event = match ($eventClass) {
            UserCreatedEvent::class => new UserCreatedEvent($user),
            UserModifiedEvent::class => new UserModifiedEvent($user),
            default => throw new \InvalidArgumentException("Unsupported event class: {$eventClass}"),
        };
        $listener($event);
    }

    /**
     * Provides test cases for user listeners.
     *
     * @return iterable<array{listenerClass: class-string, eventClass: class-string, needPersist: bool}>
     */
    public static function provideUserListenersCases(): iterable
    {
        yield 'UserCreatedListener - no persist' => [
            'listenerClass' => UserCreatedListener::class,
            'eventClass' => UserCreatedEvent::class,
            'needPersist' => false,
        ];

        yield 'UserCreatedListener - persist' => [
            'listenerClass' => UserCreatedListener::class,
            'eventClass' => UserCreatedEvent::class,
            'needPersist' => true,
        ];

        yield 'UserModifiedListener - no persist' => [
            'listenerClass' => UserModifiedListener::class,
            'eventClass' => UserModifiedEvent::class,
            'needPersist' => false,
        ];

        yield 'UserModifiedListener - persist' => [
            'listenerClass' => UserModifiedListener::class,
            'eventClass' => UserModifiedEvent::class,
            'needPersist' => true,
        ];
    }
}
