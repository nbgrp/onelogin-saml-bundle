<?php
// SPDX-License-Identifier: BSD-3-Clause

declare(strict_types=1);

namespace Nbgrp\OneloginSamlBundle\EventListener\Security;

use Nbgrp\OneloginSamlBundle\Idp\IdpResolverInterface;
use Nbgrp\OneloginSamlBundle\Onelogin\AuthRegistryInterface;
use Nbgrp\OneloginSamlBundle\Security\Http\Authenticator\SamlAuthenticator;
use Nbgrp\OneloginSamlBundle\Security\Http\Authenticator\Token\SamlToken;
use OneLogin\Saml2\Auth;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * Process Single Logout by current OneLogin Auth service on user logout.
 */
final class SamlLogoutListener
{
    public function __construct(
        private readonly AuthRegistryInterface $authRegistry,
        private readonly IdpResolverInterface $idpResolver,
    ) {}

    #[AsEventListener(LogoutEvent::class)]
    public function processSingleLogout(LogoutEvent $event): void
    {
        $authService = $this->getAuthService($event->getRequest());
        if (!$authService) {
            return;
        }

        $token = $event->getToken();
        if (!$token instanceof SamlToken) {
            return;
        }

        try {
            $authService->processSLO();
        } catch (\OneLogin\Saml2\Error) {
            if (!empty($authService->getSLOurl())) {
                /** @var string|null $sessionIndex */
                $sessionIndex = $token->hasAttribute(SamlAuthenticator::SESSION_INDEX_ATTRIBUTE)
                    ? $token->getAttribute(SamlAuthenticator::SESSION_INDEX_ATTRIBUTE)
                    : null;
                $authService->logout(null, [], $token->getUserIdentifier(), $sessionIndex);
            }
        }
    }

    private function getAuthService(Request $request): ?Auth
    {
        $idp = $this->idpResolver->resolve($request);
        if (!$idp) {
            return $this->authRegistry->getDefaultService();
        }

        if ($this->authRegistry->hasService($idp)) {
            return $this->authRegistry->getService($idp);
        }

        return null;
    }
}
