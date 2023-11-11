<?php
// SPDX-License-Identifier: BSD-3-Clause

declare(strict_types=1);

namespace Nbgrp\OneloginSamlBundle\Security\Http\Authenticator;

use Nbgrp\OneloginSamlBundle\Event\UserCreatedEvent;
use Nbgrp\OneloginSamlBundle\Event\UserModifiedEvent;
use Nbgrp\OneloginSamlBundle\Idp\IdpResolverInterface;
use Nbgrp\OneloginSamlBundle\Onelogin\AuthRegistryInterface;
use Nbgrp\OneloginSamlBundle\Security\Http\Authenticator\Passport\Badge\DeferredEventBadge;
use Nbgrp\OneloginSamlBundle\Security\Http\Authenticator\Passport\Badge\SamlAttributesBadge;
use Nbgrp\OneloginSamlBundle\Security\Http\Authenticator\Token\SamlToken;
use Nbgrp\OneloginSamlBundle\Security\User\SamlUserFactoryInterface;
use Nbgrp\OneloginSamlBundle\Security\User\SamlUserInterface;
use OneLogin\Saml2\Auth;
use OneLogin\Saml2\Utils;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\AuthenticationServiceException;
use Symfony\Component\Security\Core\Exception\LogicException;
use Symfony\Component\Security\Core\Exception\SessionUnavailableException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Security\Http\HttpUtils;

#[AutoconfigureTag('monolog.logger', ['channel' => 'security'])]
class SamlAuthenticator implements AuthenticatorInterface, AuthenticationEntryPointInterface
{
    public const SESSION_INDEX_ATTRIBUTE = '_saml_session_index';
    public const LAST_REQUEST_ID = '_saml_last_request_id';

    public function __construct(
        private readonly HttpUtils $httpUtils,
        private readonly UserProviderInterface $userProvider,
        private readonly IdpResolverInterface $idpResolver,
        private readonly AuthRegistryInterface $authRegistry,
        private readonly AuthenticationSuccessHandlerInterface $successHandler,
        private readonly AuthenticationFailureHandlerInterface $failureHandler,
        private readonly array $options,
        private readonly ?SamlUserFactoryInterface $userFactory,
        private readonly ?LoggerInterface $logger,
        private readonly string $idpParameterName,
        private readonly bool $useProxyVars,
    ) {}

    public function supports(Request $request): ?bool
    {
        return $request->isMethod('POST')
            && $this->httpUtils->checkRequestPath($request, (string) $this->options['check_path']);
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        $uri = $this->httpUtils->generateUri($request, (string) $this->options['login_path']);
        $idp = $this->idpResolver->resolve($request);
        if ($idp) {
            $uri .= '?'.$this->idpParameterName.'='.$idp;
        }

        return new RedirectResponse($uri);
    }

    public function authenticate(Request $request): Passport
    {
        if (!$request->hasSession()) {
            throw new SessionUnavailableException('This authentication method requires a session.');
        }

        $oneLoginAuth = $this->getOneLoginAuth($request);
        Utils::setProxyVars($this->useProxyVars);

        $this->processResponse($oneLoginAuth, $request->getSession());

        if ($oneLoginAuth->getErrors()) {
            $errorReason = $oneLoginAuth->getLastErrorReason() ?? 'Undefined OneLogin auth error.';
            $this->logger?->error($errorReason);

            throw new AuthenticationException($errorReason);
        }

        return $this->createPassport($oneLoginAuth);
    }

    public function createToken(Passport $passport, string $firewallName): TokenInterface
    {
        if (!$passport->hasBadge(SamlAttributesBadge::class)) {
            throw new LogicException(sprintf('Passport should contains a "%s" badge.', SamlAttributesBadge::class));
        }

        $badge = $passport->getBadge(SamlAttributesBadge::class);
        $attributes = [];

        if ($badge instanceof SamlAttributesBadge) {
            $attributes = $badge->getAttributes();
        }

        return new SamlToken($passport->getUser(), $firewallName, $passport->getUser()->getRoles(), $attributes);
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return $this->successHandler->onAuthenticationSuccess($request, $token);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return $this->failureHandler->onAuthenticationFailure($request, $exception);
    }

    protected function processResponse(Auth $oneLoginAuth, SessionInterface $session): void
    {
        $requestId = null;
        $security = $oneLoginAuth->getSettings()->getSecurityData();
        if ($security['rejectUnsolicitedResponsesWithInResponseTo'] ?? false) {
            /** @var string $requestId */
            $requestId = $session->get(self::LAST_REQUEST_ID);
        }

        $oneLoginAuth->processResponse($requestId);
    }

    protected function createPassport(Auth $oneLoginAuth): Passport
    {
        $attributes = $this->extractAttributes($oneLoginAuth);
        $this->logger?->debug('SAML attributes extracted', $attributes);

        $deferredEventBadge = new DeferredEventBadge();

        $userBadge = new UserBadge(
            $this->extractIdentifier($oneLoginAuth, $attributes),
            function (string $identifier) use ($deferredEventBadge, $attributes) {
                try {
                    try {
                        $user = $this->userProvider->loadUserByIdentifier($identifier);
                        if ($user instanceof SamlUserInterface) {
                            $user->setSamlAttributes($attributes);
                            $deferredEventBadge->setEvent(new UserModifiedEvent($user));
                        }
                    } catch (UserNotFoundException $exception) {
                        if (!$this->userFactory instanceof SamlUserFactoryInterface) {
                            throw $exception;
                        }

                        $user = $this->userFactory->createUser($identifier, $attributes);
                        $deferredEventBadge->setEvent(new UserCreatedEvent($user));
                    }
                } catch (\Throwable $exception) {
                    if ($exception instanceof UserNotFoundException) {
                        throw $exception;
                    }

                    throw new AuthenticationException('The authentication failed.', 0, $exception);
                }

                return $user;
            },
        );

        return new SelfValidatingPassport($userBadge, [
            new SamlAttributesBadge($attributes),
            $deferredEventBadge,
        ]);
    }

    protected function extractAttributes(Auth $oneLoginAuth): array
    {
        $attributes = $this->options['use_attribute_friendly_name'] ?? false
            ? $oneLoginAuth->getAttributesWithFriendlyName()
            : $oneLoginAuth->getAttributes();
        $attributes[self::SESSION_INDEX_ATTRIBUTE] = $oneLoginAuth->getSessionIndex();

        return $attributes;
    }

    protected function extractIdentifier(Auth $oneLoginAuth, array $attributes): string
    {
        if (empty($this->options['identifier_attribute'])) {
            return $oneLoginAuth->getNameId();
        }

        $identifierAttribute = (string) $this->options['identifier_attribute'];
        if (!\array_key_exists($identifierAttribute, $attributes)) {
            throw new \RuntimeException('Attribute "'.$identifierAttribute.'" not found in SAML data.');
        }

        $identifier = $attributes[$identifierAttribute];
        if (\is_array($identifier)) {
            /** @var mixed $identifier */
            $identifier = reset($identifier);
        }

        if (!\is_string($identifier)) {
            throw new \RuntimeException('Attribute "'.$identifierAttribute.'" does not contain valid user identifier.');
        }

        return $identifier;
    }

    private function getOneLoginAuth(Request $request): Auth
    {
        try {
            $idp = $this->idpResolver->resolve($request);
            $authService = $idp
                ? $this->authRegistry->getService($idp)
                : $this->authRegistry->getDefaultService();
        } catch (\RuntimeException $exception) {
            $this->logger?->error($exception->getMessage());

            throw new AuthenticationServiceException($exception->getMessage());
        }

        return $authService;
    }
}
