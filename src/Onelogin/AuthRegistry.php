<?php
// SPDX-License-Identifier: BSD-3-Clause

declare(strict_types=1);

namespace Nbgrp\OneloginSamlBundle\Onelogin;

use OneLogin\Saml2\Auth;

final class AuthRegistry implements AuthRegistryInterface
{
    /**
     * @var array<string,array<string, Auth>>
     */
    private array $services = [];

    #[\Override]
    public function addService(string $idpKey, string $spKey, Auth $auth): self
    {
        if (\array_key_exists($idpKey, $this->services)) {
            if (\array_key_exists($spKey, $this->services[$idpKey])) {
                throw new \OverflowException('Auth service with key "'.$spKey.'" already exists.');
            }
        }

        $this->services[$idpKey][$spKey] = $auth;

        return $this;
    }

    #[\Override]
    public function hasService(string $idpKey, string $spKey): bool
    {
        if (\array_key_exists($idpKey, $this->services)) {
            if (\array_key_exists($spKey, $this->services[$idpKey])) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getService(string $idpKey, string $spKey): Auth
    {
        return $this->services[$idpKey][$spKey] ?? throw new \OutOfBoundsException('Auth service for keys "'.$idpKey.' '.$spKey.'" does not exists.');
    }

    #[\Override]
    public function getDefaultService(): Auth
    {
        if ([] === $this->services) {
            throw new \UnderflowException('There is no configured Auth services.');
        }

        $firstIdp = reset($this->services);
        $firstAuth = reset($firstIdp);

        if (!$firstAuth instanceof Auth) {
            throw new \UnderflowException('There is no configured Auth services.');
        }

        return $firstAuth;
    }
}
