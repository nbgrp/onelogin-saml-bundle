<?php
// SPDX-License-Identifier: BSD-3-Clause

declare(strict_types=1);

namespace Nbgrp\OneloginSamlBundle\Onelogin;

use OneLogin\Saml2\Auth;

/**
 * Represents the interface of registry that holds OneLogin Auth services per IdP.
 */
interface AuthRegistryInterface
{
    public function addService(string $key, Auth $auth): self;

    public function hasService(string $key): bool;

    public function getService(string $key): Auth;

    public function getDefaultService(): Auth;
}
