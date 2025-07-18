<?php
// SPDX-License-Identifier: BSD-3-Clause

declare(strict_types=1);

namespace Nbgrp\OneloginSamlBundle\Security\Http\Authenticator\Passport\Badge;

use Symfony\Component\Security\Http\Authenticator\Passport\Badge\BadgeInterface;

/**
 * Allows to add SAML attributes to a passport.
 */
readonly class SamlAttributesBadge implements BadgeInterface
{
    /**
     * @param array<array-key, mixed> $attributes
     */
    public function __construct(
        private array $attributes,
    ) {}

    /**
     * @return array<array-key, mixed>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    #[\Override]
    public function isResolved(): bool
    {
        return true;
    }
}
