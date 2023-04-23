<?php
// SPDX-License-Identifier: BSD-3-Clause

declare(strict_types=1);

namespace Nbgrp\OneloginSamlBundle\Controller;

use OneLogin\Saml2\Auth;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
class Metadata
{
    public function __invoke(Auth $auth): Response
    {
        return new Response(
            content: $auth->getSettings()->getSPMetadata(),
            headers: ['Content-Type' => 'xml'],
        );
    }
}
