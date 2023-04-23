<?php
// SPDX-License-Identifier: BSD-3-Clause

declare(strict_types=1);

namespace Nbgrp\OneloginSamlBundle\Security\Http\Authenticator\Token;

use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;

class SamlToken extends PostAuthenticationToken
{
    /**
     * @param array<string> $roles
     */
    public function __construct(UserInterface $user, string $firewallName, array $roles, array $samlAttributes)
    {
        parent::__construct($user, $firewallName, $roles);

        $this->setAttributes($samlAttributes);
    }
}
