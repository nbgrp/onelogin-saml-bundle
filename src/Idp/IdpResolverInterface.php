<?php
// SPDX-License-Identifier: BSD-3-Clause

declare(strict_types=1);

namespace Nbgrp\OneloginSamlBundle\Idp;

use Symfony\Component\HttpFoundation\Request;

/**
 * Represents the interface of service that resolves the request IdP.
 */
interface IdpResolverInterface
{
    /**
     * Returns IdP name and Sp name for specified request.
     *
     * @param Request $request the request to resolve IdP for
     *
     * @return array{idp:string, sp:string} returns an array with IdP name and Sp name if they are found, or null if not
     */
    public function resolve(Request $request): array;
}
