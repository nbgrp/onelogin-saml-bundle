<?php declare(strict_types=1);
// SPDX-License-Identifier: BSD-3-Clause

namespace Nbgrp\OneloginSamlBundle\Security\User;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Represents the interface of user class that contains SAML attributes.
 */
interface SamlUserInterface extends UserInterface
{
    public function setSamlAttributes(array $attributes): void;
}
