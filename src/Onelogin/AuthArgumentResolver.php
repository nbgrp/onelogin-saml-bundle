<?php
// SPDX-License-Identifier: BSD-3-Clause

declare(strict_types=1);

namespace Nbgrp\OneloginSamlBundle\Onelogin;

use Nbgrp\OneloginSamlBundle\Idp\IdpResolverInterface;
use OneLogin\Saml2\Auth;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

/**
 * Yields the OneLogin Auth instance for current request
 * (default or according to an idp parameter).
 */
final class AuthArgumentResolver implements ValueResolverInterface
{
    public function __construct(
        private readonly AuthRegistryInterface $authRegistry,
        private readonly IdpResolverInterface $idpResolver,
    ) {}

    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        if ($argument->getType() !== Auth::class) {
            return [];
        }

        $idp = $this->idpResolver->resolve($request);
        if ($idp && !$this->authRegistry->hasService($idp)) {
            throw new BadRequestHttpException('There is no OneLogin PHP toolkit settings for IdP "'.$idp.'". See nbgrp_onelogin_saml config ("onelogin_settings" section).');
        }

        try {
            yield $idp
                ? $this->authRegistry->getService($idp)
                : $this->authRegistry->getDefaultService();
        } catch (\RuntimeException $exception) {
            throw new ServiceUnavailableHttpException($exception->getMessage());
        }
    }
}
