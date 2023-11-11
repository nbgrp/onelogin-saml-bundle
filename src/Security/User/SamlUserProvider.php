<?php
// SPDX-License-Identifier: BSD-3-Clause

declare(strict_types=1);

namespace Nbgrp\OneloginSamlBundle\Security\User;

use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Just instantiates user object with providing identifier and default roles.
 *
 * @template-covariant TUser of UserInterface
 *
 * @template-implements UserProviderInterface<TUser>
 */
class SamlUserProvider implements UserProviderInterface
{
    /**
     * @param class-string<TUser> $userClass
     */
    public function __construct(
        protected string $userClass,
        protected array $defaultRoles,
    ) {
        if (!is_a($userClass, UserInterface::class, true)) {
            throw new \InvalidArgumentException('The $userClass argument should be a class implementing the '.UserInterface::class.' interface.');
        }
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        return new $this->userClass($identifier, $this->defaultRoles);
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof $this->userClass) {
            throw new UnsupportedUserException();
        }

        return $user;
    }

    public function supportsClass(string $class): bool
    {
        return is_a($class, $this->userClass, true);
    }
}
