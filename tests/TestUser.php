<?php
// SPDX-License-Identifier: BSD-3-Clause

declare(strict_types=1);

namespace Nbgrp\Tests\OneloginSamlBundle;

use Symfony\Component\Security\Core\User\UserInterface;

final class TestUser implements UserInterface
{
    private string $email;

    /**
     * @param array<string> $roles
     */
    public function __construct(
        private string $identifier,
        private array $roles = [],
    ) {}

    public function getUserIdentifier(): string
    {
        return $this->identifier;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function eraseCredentials(): void {}

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }
}
