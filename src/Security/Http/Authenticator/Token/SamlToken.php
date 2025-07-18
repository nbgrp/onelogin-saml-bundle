<?php
// SPDX-License-Identifier: BSD-3-Clause

declare(strict_types=1);

namespace Nbgrp\OneloginSamlBundle\Security\Http\Authenticator\Token;

use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;

class SamlToken extends PostAuthenticationToken
{
    /**
     * Constructor for the SAML authentication token.
     * This token is used to store the authenticated user and their SAML attributes.
     *
     * @param array<string>          $roles
     * @param array<array-key,mixed> $samlAttributes
     */
    public function __construct(UserInterface $user, string $firewallName, array $roles, array $samlAttributes)
    {
        parent::__construct($user, $firewallName, $roles);

        $this->setAttributes($samlAttributes);
    }
}
