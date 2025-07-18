<?php
// SPDX-License-Identifier: BSD-3-Clause

declare(strict_types=1);

namespace Nbgrp\OneloginSamlBundle\EventListener\Security;

use Nbgrp\OneloginSamlBundle\Idp\IdpResolverInterface;
use Nbgrp\OneloginSamlBundle\Onelogin\AuthRegistryInterface;
use Nbgrp\OneloginSamlBundle\Security\Http\Authenticator\SamlAuthenticator;
use Nbgrp\OneloginSamlBundle\Security\Http\Authenticator\Token\SamlToken;
use OneLogin\Saml2\Auth;
use OneLogin\Saml2\Error;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * Process Single Logout by current OneLogin Auth service on user logout.
 */
final readonly class SamlLogoutListener
{
    public function __construct(
        private AuthRegistryInterface $authRegistry,
        private IdpResolverInterface $idpResolver,
    ) {}

    #[AsEventListener(LogoutEvent::class)]
    public function processSingleLogout(LogoutEvent $event): void
    {
        $authService = $this->getAuthService($event->getRequest());

        $token = $event->getToken();
        if (!$token instanceof SamlToken) {
            return;
        }

        try {
            $authService->processSLO();
        } catch (Error) {
            $sloUrl = $authService->getSLOurl();
            if (null !== $sloUrl && '' !== $sloUrl) {
                /** @var string|null $sessionIndex */
                $sessionIndex = $token->hasAttribute(SamlAuthenticator::SESSION_INDEX_ATTRIBUTE)
                    ? $token->getAttribute(SamlAuthenticator::SESSION_INDEX_ATTRIBUTE)
                    : null;

                try {
                    $authService->logout(null, [], $token->getUserIdentifier(), $sessionIndex);
                } catch (Error $e) {
                    // If logout fails, we can still redirect to SLO URL.
                    // This is useful for IdPs that do not support SLO.
                    $event->setResponse(new RedirectResponse($sloUrl));
                }
            }
        }
    }

    private function getAuthService(Request $request): Auth
    {
        $resolve = $this->idpResolver->resolve($request);
        $idp = $resolve['idp'];
        $sp = $resolve['sp'];

        return (('' !== $idp) && ('' !== $sp))
            ? $this->authRegistry->getService($idp, $sp)
            : $this->authRegistry->getDefaultService();
    }
}
