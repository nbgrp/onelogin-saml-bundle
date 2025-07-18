<?php
// SPDX-License-Identifier: BSD-3-Clause

declare(strict_types=1);

namespace Nbgrp\OneloginSamlBundle\Idp;

use Symfony\Component\HttpFoundation\Request;

final readonly class IdpResolver implements IdpResolverInterface
{
    public function __construct(
        private string $idpParameterName,
        private string $spParameterName,
    ) {}

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function resolve(Request $request): array
    {
        // Get IdP and SP names from query parameters or request attributes
        $queryIdp = (string) $request->query->get($this->idpParameterName);
        $querySp = (string) $request->query->get($this->spParameterName);
        $attributesIdp = (string) $request->attributes->get($this->idpParameterName, 'default');
        $attributesSp = (string) $request->attributes->get($this->spParameterName, 'default');

        // If query parameters are not set, use attributes
        $idp = '' !== $queryIdp ? $queryIdp : $attributesIdp;
        $sp = '' !== $querySp ? $querySp : $attributesSp;

        // If both IdP and SP are not set, return default values
        return ['idp' => $idp, 'sp' => $sp];
    }
}
