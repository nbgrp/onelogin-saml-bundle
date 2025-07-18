<?php
// SPDX-License-Identifier: BSD-3-Clause

declare(strict_types=1);

namespace Nbgrp\Tests\OneloginSamlBundle\Security\Http\Authenticator;

use Nbgrp\OneloginSamlBundle\Event\UserCreatedEvent;
use Nbgrp\OneloginSamlBundle\Event\UserModifiedEvent;
use Nbgrp\OneloginSamlBundle\Idp\IdpResolver;
use Nbgrp\OneloginSamlBundle\Idp\IdpResolverInterface;
use Nbgrp\OneloginSamlBundle\Onelogin\AuthRegistry;
use Nbgrp\OneloginSamlBundle\Onelogin\AuthRegistryInterface;
use Nbgrp\OneloginSamlBundle\Security\Http\Authenticator\Passport\Badge\DeferredEventBadge;
use Nbgrp\OneloginSamlBundle\Security\Http\Authenticator\Passport\Badge\SamlAttributesBadge;
use Nbgrp\OneloginSamlBundle\Security\Http\Authenticator\SamlAuthenticator;
use Nbgrp\OneloginSamlBundle\Security\User\SamlUserFactoryInterface;
use Nbgrp\OneloginSamlBundle\Security\User\SamlUserInterface;
use Nbgrp\Tests\OneloginSamlBundle\TestUser;
use OneLogin\Saml2\Auth;
use OneLogin\Saml2\Settings;
use OneLogin\Saml2\Utils;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Exception as MockException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\AuthenticationServiceException;
use Symfony\Component\Security\Core\Exception\SessionUnavailableException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;
use Symfony\Component\Security\Http\HttpUtils;
use Symfony\Contracts\EventDispatcher\Event;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(SamlAuthenticator::class)]
final class SamlAuthenticatorTest extends TestCase
{
    #[DataProvider('provideSupportsCases')]
    public function testSupports(Request $request, bool $expectedSupports): void
    {
        $authenticator = $this->createSamlAuthenticator(
            httpUtils: new HttpUtils(),
            options: ['check_path' => '/check'],
        );

        self::assertSame($expectedSupports, $authenticator->supports($request));
    }

    /** @return iterable<array<array-key,mixed>> */
    public static function provideSupportsCases(): iterable
    {
        yield 'GET request' => [
            'request' => Request::create('/'),
            'expectedSupports' => false,
        ];

        yield 'Not check request' => [
            'request' => Request::create('/', 'POST'),
            'expectedSupports' => false,
        ];

        yield 'Check request' => [
            'request' => Request::create('/check', 'POST'),
            'expectedSupports' => true,
        ];
    }

    #[DataProvider('provideStartCases')]
    public function testStart(Request $request, string $idpParameterName, string $spParameterName, string $expectedLocation): void
    {
        $authenticator = $this->createSamlAuthenticator(
            httpUtils: new HttpUtils(),
            idpResolver: new IdpResolver($idpParameterName, $spParameterName),
            options: ['login_path' => '/login'],
            idpParameterName: $idpParameterName,
            spParameterName: $spParameterName,
        );
        $response = $authenticator->start($request);

        self::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        self::assertSame($expectedLocation, $response->headers->get('Location'));
    }

    /** @return iterable<array<array-key,mixed>> */
    public static function provideStartCases(): iterable
    {
        yield 'Without idp' => [
            'request' => Request::create('/'),
            'idpParameterName' => 'idp',
            'spParameterName' => 'sp',
            'expectedLocation' => 'http://localhost/login?idp=default&sp=default',
        ];

        yield 'With idp only' => [
            'request' => Request::create('/', 'GET', ['fw' => 'custom']),
            'idpParameterName' => 'fw',
            'spParameterName' => 'sp',
            'expectedLocation' => 'http://localhost/login?fw=custom&sp=default',
        ];

        yield 'With idp and sp' => [
            'request' => Request::create('/', 'GET', ['fw' => 'custom_idp', 'fw2' => 'custom_sp']),
            'idpParameterName' => 'fw',
            'spParameterName' => 'fw2',
            'expectedLocation' => 'http://localhost/login?fw=custom_idp&fw2=custom_sp',
        ];
    }

    public function testAuthenticateSessionException(): void
    {
        $authenticator = $this->createSamlAuthenticator();

        $this->expectException(SessionUnavailableException::class);
        $this->expectExceptionMessage('This authentication method requires a session.');

        $authenticator->authenticate(Request::create('/'));
    }

    /**
     * @param array<array-key,mixed> $idpResolveValue
     * @param array<array-key,mixed> $authServiceKey
     * @param array<array-key,mixed> $authErrors
     */
    #[DataProvider('provideAuthenticateOneLoginErrorsExceptionCases')]
    public function testAuthenticateOneLoginErrorsException(
        array $idpResolveValue,
        array $authServiceKey,
        array $authErrors,
        ?string $lastErrorReason,
        string $expectedMessage,
    ): void {
        $request = Request::create('/');
        $request->setSession(new Session(new MockArraySessionStorage()));

        try {
            $logger = $this->createMock(LoggerInterface::class);
            $logger
                ->expects(self::once())
                ->method('error')
                ->with($expectedMessage)
            ;

            $idpResolver = $this->createConfiguredMock(IdpResolverInterface::class,
                ['resolve' => $idpResolveValue]
            );

            /** @var Settings&MockObject $settingsMock */
            $settingsMock = $this->createMock(Settings::class);
            $settingsMock
                ->method('getSecurityData')
                ->willReturn([])
            ;

            /** @var Auth&MockObject $auth */
            $auth = $this->createConfiguredMock(Auth::class, [
                'getErrors' => $authErrors,
                'getLastErrorReason' => $lastErrorReason,
            ]);
            $auth
                ->expects(self::once())
                ->method('processResponse')
            ;
            $auth
                ->method('getSettings')
                ->willReturn($settingsMock)
            ;
        } catch (MockException $e) {
            self::fail('Failed to create mocks for OneLogin Auth. '.$e->getMessage());
        }

        $authRegistry = new AuthRegistry();
        [$idpKey, $spKey] = $authServiceKey;
        $authRegistry->addService((string) $idpKey, (string) $spKey, $auth);

        $authenticator = $this->createSamlAuthenticator(
            idpResolver: $idpResolver,
            authRegistry: $authRegistry,
            logger: $logger,
        );

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage($expectedMessage);

        $authenticator->authenticate($request);
    }

    /** @return iterable<array<array-key,mixed>> */
    public static function provideAuthenticateOneLoginErrorsExceptionCases(): iterable
    {
        yield 'Default Auth service + OneLogin auth error' => [
            'idpResolveValue' => ['idp' => 'default', 'sp' => 'default'],
            'authServiceKey' => ['default', 'default'],
            'authErrors' => ['invalid something'],
            'lastErrorReason' => 'error reason',
            'expectedMessage' => 'error reason',
        ];

        yield 'Custom Auth service + undefined OneLogin auth error' => [
            'idpResolveValue' => ['idp' => 'custom', 'sp' => 'default'],
            'authServiceKey' => ['custom', 'default'],
            'authErrors' => ['invalid something'],
            'lastErrorReason' => null,
            'expectedMessage' => 'Undefined OneLogin auth error.',
        ];
    }

    public function testAuthenticateWithoutAuthServiceException(): void
    {
        try {
            $request = Request::create('/');
            $request->setSession(new Session(new MockArraySessionStorage()));

            $idpResolver = $this->createConfiguredMock(IdpResolverInterface::class, [
                'resolve' => ['idp' => '', 'sp' => ''],
            ]);
            $authenticator = $this->createSamlAuthenticator(
                idpResolver: $idpResolver,
                authRegistry: new AuthRegistry(),
            );

            $this->expectException(AuthenticationServiceException::class);
            $this->expectExceptionMessage('There is no configured Auth services.');

            $authenticator->authenticate($request);
        } catch (MockException $e) {
            self::fail('Failed to create mocks for OneLogin Auth. '.$e->getMessage());
        }
    }

    /**
     * @param array<array-key,mixed> $attributes
     */
    #[DataProvider('provideSuccessAuthenticateCases')]
    public function testSuccessAuthenticate(
        array $attributes,
        bool $friendly,
        ?string $identifier_attribute,
        ?string $nameId,
        string $sessionIndex,
        bool $userFound,
        bool $samlUserFactory,
        ?string $lastRequestId,
        bool $useProxyVars,
        string $expectedIdentifier,
        ?string $expectedEventClass,
    ): void {
        $request = Request::create('/');
        $session = new Session(new MockArraySessionStorage());
        if ($lastRequestId !== null) {
            $session->set(SamlAuthenticator::LAST_REQUEST_ID, $lastRequestId);
        }
        $request->setSession($session);

        try {
            $settings = self::createStub(Settings::class);
            $settings->method('getSecurityData')
                ->willReturn(['rejectUnsolicitedResponsesWithInResponseTo' => (bool) $lastRequestId])
            ;

            $auth = $this->createMock(Auth::class);
            if ($friendly) {
                $auth->method('getAttributesWithFriendlyName')->willReturn($attributes);
            } else {
                $auth->method('getAttributes')->willReturn($attributes);
            }

            $auth->method('getSessionIndex')->willReturn($sessionIndex);
            $auth->method('getSettings')->willReturn($settings);
            $auth->method('getErrors')->willReturn([]);
            $auth->method('processResponse')->with($lastRequestId);
            $auth->method('getNameId')->willReturn($nameId);

            $userProvider = self::createStub(UserProviderInterface::class);
            if ($userFound) {
                $user = $this->createConfiguredMock(SamlUserInterface::class, [
                    'getUserIdentifier' => $expectedIdentifier,
                ]);
                $user->method('setSamlAttributes');
                $userProvider->method('loadUserByIdentifier')->willReturn($user);
            } else {
                $userProvider->method('loadUserByIdentifier')->willThrowException(new UserNotFoundException());
            }

            $samlUserFactoryMock = null;
            if ($samlUserFactory) {
                $user = $this->createConfiguredMock(SamlUserInterface::class, [
                    'getUserIdentifier' => $expectedIdentifier,
                ]);
                $user->method('setSamlAttributes');

                $samlUserFactoryMock = $this->createMock(SamlUserFactoryInterface::class);
                $samlUserFactoryMock
                    ->method('createUser')
                    ->willReturn($user)
                ;
            }

            $eventDispatcherMock = null;
            if (isset($expectedEventClass) && $expectedEventClass !== '') {
                $eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
                $eventDispatcherMock
                    ->expects(self::once())
                    ->method('dispatch')
                    ->with(self::isInstanceOf($expectedEventClass))
                ;
            }
            $idpResolver = $this->createConfiguredMock(IdpResolverInterface::class, ['resolve' => ['idp' => 'foo', 'sp' => 'boo']]);
        } catch (MockException $e) {
            self::fail('Failed to create mocks for OneLogin Auth. '.$e->getMessage());
        }

        $authRegistry = new AuthRegistry();
        $authRegistry->addService('foo', 'boo', $auth);

        $options = ['use_attribute_friendly_name' => $friendly];
        if ($identifier_attribute !== null) {
            $options['identifier_attribute'] = $identifier_attribute;
        }

        self::assertFalse(Utils::getProxyVars());

        $authenticator = $this->createSamlAuthenticator(
            userProvider: $userProvider,
            idpResolver: $idpResolver,
            authRegistry: $authRegistry,
            options: $options,
            samlUserFactory: $samlUserFactoryMock,
            useProxyVars: $useProxyVars,
        );

        $passport = $authenticator->authenticate($request);

        self::assertSame($useProxyVars, Utils::getProxyVars());
        self::assertSame($expectedIdentifier, $passport->getUser()->getUserIdentifier());

        /** @var SamlAttributesBadge $badge */
        $badge = $passport->getBadge(SamlAttributesBadge::class);
        $expectedAttrs = $attributes;
        $expectedAttrs[SamlAuthenticator::SESSION_INDEX_ATTRIBUTE] = $sessionIndex;
        self::assertSame($expectedAttrs, $badge->getAttributes());

        if (isset($expectedEventClass) && $expectedEventClass !== '') {
            $deferredBadge = $passport->getBadge(DeferredEventBadge::class);
            self::assertInstanceOf(DeferredEventBadge::class, $deferredBadge);
            $event = $deferredBadge->getEvent();
            if ($event instanceof Event) {
                /** @var EventDispatcherInterface&MockObject $eventDispatcherMock */
                $eventDispatcherMock->dispatch($event);
            } else {
                self::fail('Expected event is not set in DeferredEventBadge.');
            }

            if ($expectedEventClass === UserCreatedEvent::class) {
                self::assertInstanceOf(UserCreatedEvent::class, $deferredBadge->getEvent());
            } elseif ($expectedEventClass === UserModifiedEvent::class) {
                self::assertInstanceOf(UserModifiedEvent::class, $deferredBadge->getEvent());
            } else {
                self::fail('Unexpected event class: '.$expectedEventClass);
            }
        }
    }

    /** @return iterable<array<array-key,mixed>> */
    public static function provideSuccessAuthenticateCases(): iterable
    {
        yield 'Not attribute friendly name + user identifier from OneLogin auth' => [
            'attributes' => ['username' => 'tester', 'email' => 'tester@example.com'],
            'friendly' => false,
            'identifier_attribute' => null,
            'nameId' => 'tester_id',
            'sessionIndex' => 'session_index',
            'userFound' => true,
            'samlUserFactory' => false,
            'lastRequestId' => null,
            'useProxyVars' => false,
            'expectedIdentifier' => 'tester_id',
            'expectedEventClass' => null,
        ];

        yield 'Friendly name + user created via factory' => [
            'attributes' => ['username' => ['tester_attribute'], 'email' => 'tester@example.com'],
            'friendly' => true,
            'identifier_attribute' => 'username',
            'nameId' => null,
            'sessionIndex' => 'session_index',
            'userFound' => false,
            'samlUserFactory' => true,
            'lastRequestId' => null,
            'useProxyVars' => false,
            'expectedIdentifier' => 'tester_attribute',
            'expectedEventClass' => UserCreatedEvent::class,
        ];

        yield 'Friendly name + user loaded and modified' => [
            'attributes' => ['username' => 'tester_attribute', 'email' => 'tester@example.com'],
            'friendly' => true,
            'identifier_attribute' => 'username',
            'nameId' => null,
            'sessionIndex' => 'session_index',
            'userFound' => true,
            'samlUserFactory' => false,
            'lastRequestId' => 'requestID',
            'useProxyVars' => true,
            'expectedIdentifier' => 'tester_attribute',
            'expectedEventClass' => UserModifiedEvent::class,
        ];
    }

    /**
     * @param array<string,mixed>      $attributes
     * @param array<string,mixed>      $options
     * @param class-string<\Throwable> $expectedException
     */
    #[DataProvider('provideAuthenticateExceptionWithProviderCases')]
    public function testAuthenticateExceptionWithProvider(
        array $attributes,
        ?string $nameId,
        ?string $userProviderFlag,
        ?string $samlUserFactoryFlag,
        array $options,
        string $expectedException,
        ?string $expectedMessage,
    ): void {
        $request = Request::create('/');
        $request->setSession(new Session(new MockArraySessionStorage()));

        try {
            $settingsMock = self::createConfiguredMock(Settings::class, [
                'getSecurityData' => [],
            ]);

            $auth = self::createConfiguredMock(Auth::class, [
                'getAttributes' => $attributes,
                'getSessionIndex' => 'session_index',
                'getSettings' => $settingsMock,
                'getErrors' => [],
            ]);
            if ($nameId !== null) {
                $auth->method('getNameId')->willReturn($nameId);
            }

            $userProvider = null;
            if ($userProviderFlag === 'not_found') {
                $userProvider = self::createMock(UserProviderInterface::class);
                $userProvider
                    ->method('loadUserByIdentifier')
                    ->willThrowException(new UserNotFoundException())
                ;
            }

            $samlUserFactory = null;
            if ($samlUserFactoryFlag === 'factory_fails') {
                $samlUserFactory = self::createMock(SamlUserFactoryInterface::class);
                $samlUserFactory
                    ->method('createUser')
                    ->willThrowException(new \Exception())
                ;
            }

            $idpResolver = self::createConfiguredMock(IdpResolverInterface::class, [
                'resolve' => ['idp' => 'foo', 'sp' => 'boo'],
            ]);
        } catch (MockException $e) {
            self::fail('Failed to create mocks for OneLogin Auth. '.$e->getMessage());
        }

        $authRegistry = new AuthRegistry();
        $authRegistry->addService('foo', 'boo', $auth);

        $authenticator = $this->createSamlAuthenticator(
            userProvider: $userProvider,
            idpResolver: $idpResolver,
            authRegistry: $authRegistry,
            options: $options,
            samlUserFactory: $samlUserFactory,
        );

        $this->expectException($expectedException);
        if ($expectedMessage !== null) {
            $this->expectExceptionMessage($expectedMessage);
        }

        $authenticator->authenticate($request)->getUser();
    }

    /** @return iterable<array<array-key,mixed>> */
    public static function provideAuthenticateExceptionWithProviderCases(): iterable
    {
        yield 'missing identifier attribute' => [
            [],
            null,
            null,
            null,
            ['identifier_attribute' => 'username'],
            \RuntimeException::class,
            'Attribute "username" not found in SAML data.',
        ];

        yield 'invalid identifier attribute (empty array)' => [
            ['username' => []],
            null,
            null,
            null,
            ['identifier_attribute' => 'username'],
            \RuntimeException::class,
            'Attribute "username" does not contain valid user identifier.',
        ];

        yield 'user not found, no user factory' => [
            [],
            'tester_id',
            'not_found',
            null,
            [],
            UserNotFoundException::class,
            null,
        ];

        yield 'user factory fails' => [
            [],
            'tester_id',
            'not_found',
            'factory_fails',
            [],
            AuthenticationException::class,
            'The authentication failed.',
        ];
    }

    /**
     * @param UserProviderInterface<SamlUserInterface>|null $userProvider
     * @param array<string,mixed>                           $options
     */
    private function createSamlAuthenticator(
        ?HttpUtils $httpUtils = null,
        ?UserProviderInterface $userProvider = null,
        ?IdpResolverInterface $idpResolver = null,
        ?AuthRegistryInterface $authRegistry = null,
        ?AuthenticationSuccessHandlerInterface $authenticationSuccessHandler = null,
        ?AuthenticationFailureHandlerInterface $authenticationFailureHandler = null,
        array $options = [],
        ?SamlUserFactoryInterface $samlUserFactory = null,
        ?LoggerInterface $logger = null,
        string $idpParameterName = 'idp',
        string $spParameterName = 'sp',
        bool $useProxyVars = false,
    ): SamlAuthenticator {
        try {
            return new SamlAuthenticator(
                $httpUtils ?? self::createStub(HttpUtils::class),
                $userProvider ?? self::createStub(UserProviderInterface::class),
                $idpResolver ?? self::createStub(IdpResolverInterface::class),
                $authRegistry ?? self::createStub(AuthRegistryInterface::class),
                $authenticationSuccessHandler ?? self::createStub(AuthenticationSuccessHandlerInterface::class),
                $authenticationFailureHandler ?? self::createStub(AuthenticationFailureHandlerInterface::class),
                $options,
                $samlUserFactory,
                $logger,
                $idpParameterName,
                $spParameterName,
                $useProxyVars,
            );
        } catch (\Throwable $e) {
            self::fail('Failed to create SamlAuthenticator instance.'.$e->getMessage());
        }
    }

    public function testCreateToken(): void
    {
        $authenticator = $this->createSamlAuthenticator();
        $passport = new SelfValidatingPassport(
            new UserBadge('tester', static fn (): TestUser => new TestUser('tester', ['ROLE_EXTRA_USER'])),
            [new SamlAttributesBadge(['username' => 'tester'])],
        );

        /** @var PostAuthenticationToken $token */
        $token = $authenticator->createToken($passport, 'firewallName');

        self::assertSame('tester', $token->getUserIdentifier());
        self::assertSame(['ROLE_EXTRA_USER'], $token->getRoleNames());
        self::assertSame('firewallName', $token->getFirewallName());
        self::assertSame(['username' => 'tester'], $token->getAttributes());
    }

    public function testCreateTokenWithoutSamlAttributesBadgeException(): void
    {
        $authenticator = $this->createSamlAuthenticator();
        $passport = new SelfValidatingPassport(new UserBadge('tester'));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Passport should contains a "Nbgrp\OneloginSamlBundle\Security\Http\Authenticator\Passport\Badge\SamlAttributesBadge" badge.');

        $authenticator->createToken($passport, 'firewallName');
    }

    public function testOnAuthenticationSuccess(): void
    {
        try {
            $request = self::createStub(Request::class);
            $token = self::createStub(TokenInterface::class);

            $authenticationSuccessHandler = self::createMock(AuthenticationSuccessHandlerInterface::class);
            $authenticationSuccessHandler
                ->method('onAuthenticationSuccess')
                ->with($request, $token)
            ;
        } catch (MockException $e) {
            self::fail('Failed to create mock for AuthenticationSuccessHandlerInterface. '.$e->getMessage());
        }

        $authenticator = $this->createSamlAuthenticator(
            authenticationSuccessHandler: $authenticationSuccessHandler,
        );

        $authenticator->onAuthenticationSuccess($request, $token, 'firewallName');
    }

    public function testOnAuthenticationFailure(): void
    {
        try {
            $request = self::createStub(Request::class);
            $exception = new AuthenticationException();

            $authenticationFailureHandler = self::createMock(AuthenticationFailureHandlerInterface::class);
            $authenticationFailureHandler
                ->method('onAuthenticationFailure')
                ->with($request, $exception)
            ;
        } catch (MockException $e) {
            self::fail('Failed to create mock for AuthenticationFailureHandlerInterface. '.$e->getMessage());
        }
        $authenticator = $this->createSamlAuthenticator(
            authenticationFailureHandler: $authenticationFailureHandler,
        );

        $authenticator->onAuthenticationFailure($request, $exception);
    }
}
