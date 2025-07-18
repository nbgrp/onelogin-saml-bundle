<?php
// SPDX-License-Identifier: BSD-3-Clause

declare(strict_types=1);

namespace Nbgrp\OneloginSamlBundle\Security\User;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Represents the interface of user class that contains SAML attributes.
 */
interface SamlUserInterface extends UserInterface
{
    /**
     * Sets the SAML attributes for the user.
     *
     * @param array<array-key, mixed> $attributes
     */
    public function setSamlAttributes(array $attributes): void;
}
