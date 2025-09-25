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
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @internal
 */
#[CoversClass(AbstractUserEvent::class)]
#[CoversClass(AbstractUserListener::class)]
#[CoversClass(UserCreatedListener::class)]
#[CoversClass(UserModifiedListener::class)]
final class UserListenersTest extends TestCase
{
    /**
     * @param callable(TestCase): EntityManagerInterface $entityManager
     */
    #[DataProvider('provideUserListenerCases')]
    public function testUserCreatedListener(
        callable $entityManager,
        bool $needPersist,
        UserInterface $user,
    ): void {
        new UserCreatedListener($entityManager($this), $needPersist)(new UserCreatedEvent($user));
    }

    /**
     * @param callable(TestCase): EntityManagerInterface $entityManager
     */
    #[DataProvider('provideUserListenerCases')]
    public function testUserModifiedListener(
        callable $entityManager,
        bool $needPersist,
        UserInterface $user,
    ): void {
        new UserModifiedListener($entityManager($this), $needPersist)(new UserModifiedEvent($user));
    }

    public static function provideUserListenerCases(): iterable
    {
        yield 'needPersist false' => [
            'entityManager' => static function (TestCase $case): EntityManagerInterface {
                $entityManager = $case->createMock(EntityManagerInterface::class);
                $entityManager
                    ->expects($case->never())
                    ->method('persist')
                ;
                $entityManager
                    ->expects($case->never())
                    ->method('flush')
                ;

                return $entityManager;
            },
            'needPersist' => false,
            'user' => new TestUser('tester'),
        ];

        $user = new TestUser('tester');
        yield 'Success' => [
            'entityManager' => static function (TestCase $case) use ($user): EntityManagerInterface {
                $entityManager = $case->createMock(EntityManagerInterface::class);
                $entityManager
                    ->expects($case->once())
                    ->method('persist')
                    ->with($user)
                ;
                $entityManager
                    ->expects($case->once())
                    ->method('flush')
                ;

                return $entityManager;
            },
            'needPersist' => true,
            'user' => $user,
        ];
    }
}
