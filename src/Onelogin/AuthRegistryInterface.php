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
    public function addService(string $idpKey, string $spKey, Auth $auth): self;

    public function hasService(string $idpKey, string $spKey): bool;

    /**
     * Get the Auth service for the given IdP and SP keys.
     *
     * @throws \OutOfBoundsException if the service does not exist
     */
    public function getService(string $idpKey, string $spKey): Auth;

    public function getDefaultService(): Auth;
}
