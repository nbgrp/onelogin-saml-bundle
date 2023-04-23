<?php
// SPDX-License-Identifier: BSD-3-Clause

declare(strict_types=1);

namespace Nbgrp\OneloginSamlBundle\Idp;

use Symfony\Component\HttpFoundation\Request;

final class IdpResolver implements IdpResolverInterface
{
    public function __construct(
        private readonly string $idpParameterName,
    ) {}

    public function resolve(Request $request): ?string
    {
        if ($request->query->has($this->idpParameterName)) {
            return (string) $request->query->get($this->idpParameterName);
        }

        if ($request->attributes->has($this->idpParameterName)) {
            /** @phpstan-ignore-next-line */
            return (string) $request->attributes->get($this->idpParameterName);
        }

        return null;
    }
}
