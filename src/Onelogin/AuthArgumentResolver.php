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

/**
 * Yields the OneLogin Auth instance for current request
 * (default or according to an idp parameter).
 */
final readonly class AuthArgumentResolver implements ValueResolverInterface
{
    public function __construct(
        private AuthRegistryInterface $authRegistry,
        private IdpResolverInterface $idpResolver,
    ) {}

    /** @phpstan-ignore-next-line  */
    #[\Override]
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        if (Auth::class !== $argument->getType()) {
            return [];
        }

        $resolve = $this->idpResolver->resolve($request);
        $idp = $resolve['idp'];
        $sp = $resolve['sp'];
        if (!$this->authRegistry->hasService($idp, $sp)) {
            throw new BadRequestHttpException('There is no OneLogin PHP toolkit settings for IdP "'.$idp.'" and SP "'.$sp.'". See nbgrp_onelogin_saml config ("onelogin_settings" section).');
        }
        yield ('default' !== $idp && 'default' !== $sp)
            ? $this->authRegistry->getService($idp, $sp)
            : $this->authRegistry->getDefaultService();
    }
}
