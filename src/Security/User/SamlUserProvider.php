<?php declare(strict_types=1);
// SPDX-License-Identifier: BSD-3-Clause

namespace Nbgrp\OneloginSamlBundle\Security\User;

use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Just instantiates user object with providing identifier and default roles.
 */
class SamlUserProvider implements UserProviderInterface
{
    /**
     * @param class-string<UserInterface> $userClass
     */
    public function __construct(
        protected string $userClass,
        protected array $defaultRoles,
    ) {}

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
        return $this->userClass === $class || is_subclass_of($class, $this->userClass);
    }
}
