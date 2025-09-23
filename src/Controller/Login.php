<?php
// SPDX-License-Identifier: BSD-3-Clause

declare(strict_types=1);

namespace Nbgrp\OneloginSamlBundle\Controller;

use Nbgrp\OneloginSamlBundle\Security\Http\Authenticator\SamlAuthenticator;
use OneLogin\Saml2\Auth;
use Symfony\Bundle\SecurityBundle\Security\FirewallMap;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

#[AsController]
class Login
{
    public function __construct(
        private readonly FirewallMap $firewallMap,
        private readonly array $authnRequestParams,
    ) {}

    public function __invoke(Request $request, Auth $auth): RedirectResponse
    {
        $targetPath = null;
        $session = null;
        /** @var \Throwable|null $error */
        $error = $request->attributes->get(SecurityRequestAttributes::AUTHENTICATION_ERROR);

        if ($request->hasSession()) {
            $session = $request->getSession();
            $targetPath = $this->getTargetPath($request, $session);

            if ($session->has(SecurityRequestAttributes::AUTHENTICATION_ERROR)) {
                /** @var \Throwable|null $error */
                $error = $session->get(SecurityRequestAttributes::AUTHENTICATION_ERROR);
                $session->remove(SecurityRequestAttributes::AUTHENTICATION_ERROR);
            }
        }

        if ($error instanceof \Throwable) {
            throw new \RuntimeException($error->getMessage());
        }

        return new RedirectResponse($this->processLoginAndGetRedirectUrl($auth, $targetPath, $session));
    }

    /** @psalm-suppress MixedReturnStatement */
    private function getTargetPath(Request $request, SessionInterface $session): ?string
    {
        $firewallName = $this->firewallMap->getFirewallConfig($request)?->getName();
        if ($firewallName === null || $firewallName === '') {
            throw new ServiceUnavailableHttpException(message: 'Unknown firewall.');
        }

        /** @phpstan-ignore-next-line */
        return $session->get('_security.'.$firewallName.'.target_path');
    }

    /** @psalm-suppress MixedArgument */
    private function processLoginAndGetRedirectUrl(Auth $auth, ?string $targetPath, ?SessionInterface $session): string
    {
        $redirectUrl = $auth->login(
            returnTo: $targetPath,
            parameters: $this->authnRequestParams['parameters'] ?? [],
            forceAuthn: $this->authnRequestParams['forceAuthn'] ?? false,
            isPassive: $this->authnRequestParams['isPassive'] ?? false,
            stay: true,
            setNameIdPolicy: $this->authnRequestParams['setNameIdPolicy'] ?? true,
            nameIdValueReq: ($this->authnRequestParams['nameIdValueReq'] ?? null),
        );

        $security = $auth->getSettings()->getSecurityData();
        if (($security['rejectUnsolicitedResponsesWithInResponseTo'] ?? false) !== false && $session instanceof SessionInterface) {
            $session->set(SamlAuthenticator::LAST_REQUEST_ID, $auth->getLastRequestID());
        }

        // @phan-suppress-next-line PhanPossiblyNullTypeReturn
        return $redirectUrl;
    }
}
