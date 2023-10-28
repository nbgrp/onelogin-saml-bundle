<?php
// SPDX-License-Identifier: BSD-3-Clause

declare(strict_types=1);

namespace Nbgrp\Tests\OneloginSamlBundle\EventListener\User;

use Doctrine\ORM\EntityManagerInterface;
use Nbgrp\OneloginSamlBundle\Event\UserCreatedEvent;
use Nbgrp\OneloginSamlBundle\Event\UserModifiedEvent;
use Nbgrp\OneloginSamlBundle\EventListener\User\UserCreatedListener;
use Nbgrp\OneloginSamlBundle\EventListener\User\UserModifiedListener;
use Nbgrp\Tests\OneloginSamlBundle\TestUser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @covers \Nbgrp\OneloginSamlBundle\Event\AbstractUserEvent
 * @covers \Nbgrp\OneloginSamlBundle\EventListener\User\AbstractUserListener
 * @covers \Nbgrp\OneloginSamlBundle\EventListener\User\UserCreatedListener
 * @covers \Nbgrp\OneloginSamlBundle\EventListener\User\UserModifiedListener
 *
 * @internal
 */
final class UserListenersTest extends TestCase
{
    /**
     * @dataProvider userListenerProvider
     */
    public function testUserCreatedListener(?EntityManagerInterface $entityManager, bool $needPersist, UserInterface $user): void
    {
        (new UserCreatedListener($entityManager, $needPersist))(new UserCreatedEvent($user));
    }

    /**
     * @dataProvider userListenerProvider
     */
    public function testUserModifiedListener(?EntityManagerInterface $entityManager, bool $needPersist, UserInterface $user): void
    {
        (new UserModifiedListener($entityManager, $needPersist))(new UserModifiedEvent($user));
    }

    public function userListenerProvider(): iterable
    {
        yield 'needPersist false' => (function (): array {
            $entityManager = $this->createMock(EntityManagerInterface::class);
            $entityManager
                ->expects(self::never())
                ->method('persist')
            ;
            $entityManager
                ->expects(self::never())
                ->method('flush')
            ;

            return [
                'entityManager' => $entityManager,
                'needPersist' => false,
                'user' => new TestUser('tester'),
            ];
        })();

        yield 'Success' => (function (): array {
            $user = new TestUser('tester');

            $entityManager = $this->createMock(EntityManagerInterface::class);
            $entityManager
                ->expects(self::once())
                ->method('persist')
                ->with($user)
            ;
            $entityManager
                ->expects(self::once())
                ->method('flush')
            ;

            return [
                'entityManager' => $entityManager,
                'needPersist' => true,
                'user' => $user,
            ];
        })();
    }
}
