<?php
// SPDX-License-Identifier: BSD-3-Clause

declare(strict_types=1);

namespace Nbgrp\OneloginSamlBundle\Controller;

use OneLogin\Saml2\Auth;
use OneLogin\Saml2\Error as Saml2Exception;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
readonly class Metadata
{
    /**
     * @throws Saml2Exception
     */
    public function __invoke(Auth $auth): Response
    {
        return new Response(
            content: $auth->getSettings()->getSPMetadata(),
            headers: ['Content-Type' => 'xml'],
        );
    }
}
