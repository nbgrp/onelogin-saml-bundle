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
    public function testStart(Request $request, string $idpParameterName, string $expectedLocation): void
    {
        $authenticator = $this->createSamlAuthenticator(
            httpUtils: new HttpUtils(),
            idpResolver: new IdpResolver($idpParameterName),
            options: ['login_path' => '/login'],
            idpParameterName: $idpParameterName,
        );
        $response = $authenticator->start($request);

        self::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        self::assertSame($expectedLocation, $response->headers->get('Location'));
    }

    public static function provideStartCases(): iterable
    {
        yield 'Without idp' => [
            'request' => Request::create('/'),
            'idpParameterName' => 'idp',
            'expectedLocation' => 'http://localhost/login',
        ];

        yield 'With idp' => [
            'request' => Request::create('/', 'GET', ['fw' => 'custom']),
            'idpParameterName' => 'fw',
            'expectedLocation' => 'http://localhost/login?fw=custom',
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
     * @param callable(TestCase): IdpResolverInterface  $idpResolver
     * @param callable(TestCase): AuthRegistryInterface $authRegistry
     */
    #[DataProvider('provideAuthenticateOneLoginErrorsExceptionCases')]
    public function testAuthenticateOneLoginErrorsException(
        callable $idpResolver,
        callable $authRegistry,
        string $expectedMessage,
    ): void {
        $request = Request::create('/');
        $request->setSession(new Session(new MockArraySessionStorage()));

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->method('error')
            ->with($expectedMessage)
        ;

        $authenticator = $this->createSamlAuthenticator(
            idpResolver: $idpResolver($this),
            authRegistry: $authRegistry($this),
            logger: $logger,
        );

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage($expectedMessage);

        $authenticator->authenticate($request);
    }

    public static function provideAuthenticateOneLoginErrorsExceptionCases(): iterable
    {
        yield 'Default Auth service + OneLogin auth error' => [
            'idpResolver' => static fn (TestCase $case): IdpResolverInterface => $case->createConfiguredMock(IdpResolverInterface::class, [
                'resolve' => null,
            ]),
            'authRegistry' => static function (TestCase $case): AuthRegistryInterface {
                $auth = $case->createConfiguredMock(Auth::class, [
                    'getErrors' => ['invalid something'],
                    'getLastErrorReason' => 'error reason',
                ]);
                $auth
                    ->expects($case->once())
                    ->method('processResponse')
                ;
                $settingsMock = $case->createMock(Settings::class);
                $settingsMock
                    ->method('getSecurityData')
                    ->willReturn([])
                ;
                $auth
                    ->expects($case->once())
                    ->method('getSettings')
                    ->willReturn($settingsMock)
                ;
                $authRegistry = new AuthRegistry();
                $authRegistry->addService('foo', $auth);

                return $authRegistry;
            },
            'expectedMessage' => 'error reason',
        ];

        yield 'Custom Auth service + undefined OneLogin auth error' => [
            'idpResolver' => static fn (TestCase $case): IdpResolverInterface => $case->createConfiguredMock(IdpResolverInterface::class, [
                'resolve' => 'custom',
            ]),
            'authRegistry' => static function (TestCase $case): AuthRegistryInterface {
                $auth = $case->createConfiguredMock(Auth::class, [
                    'getErrors' => ['invalid something'],
                    'getLastErrorReason' => null,
                ]);
                $auth
                    ->expects($case->once())
                    ->method('processResponse')
                ;
                $settingsMock = $case->createMock(Settings::class);
                $settingsMock
                    ->method('getSecurityData')
                    ->willReturn([])
                ;
                $auth
                    ->expects($case->once())
                    ->method('getSettings')
                    ->willReturn($settingsMock)
                ;
                $authRegistry = new AuthRegistry();
                $authRegistry->addService('custom', $auth);

                return $authRegistry;
            },
            'expectedMessage' => 'Undefined OneLogin auth error.',
        ];
    }

    public function testAuthenticateWithoutAuthServiceException(): void
    {
        $request = Request::create('/');
        $request->setSession(new Session(new MockArraySessionStorage()));

        $idpResolver = $this->createConfiguredMock(IdpResolverInterface::class, [
            'resolve' => null,
        ]);
        $authenticator = $this->createSamlAuthenticator(
            idpResolver: $idpResolver,
            authRegistry: new AuthRegistry(),
        );

        $this->expectException(AuthenticationServiceException::class);
        $this->expectExceptionMessage('There is no configured Auth services.');

        $authenticator->authenticate($request);
    }

    /**
     * @param callable(TestCase): Auth                      $auth
     * @param ?callable(TestCase): UserProviderInterface    $userProvider
     * @param ?callable(TestCase): SamlUserFactoryInterface $samlUserFactory
     * @param ?callable(TestCase): EventDispatcherInterface $eventDispatcher
     */
    #[DataProvider('provideSuccessAuthenticateCases')]
    public function testSuccessAuthenticate(
        callable $auth,
        ?callable $userProvider,
        ?callable $samlUserFactory,
        ?callable $eventDispatcher,
        array $options,
        ?string $lastRequestId,
        bool $useProxyVars,
        string $expectedUserIdentifier,
        array $expectedSamlAttributes,
        bool $expectedUseProxyVars,
    ): void {
        $request = Request::create('/');
        $session = new Session(new MockArraySessionStorage());
        if ($lastRequestId) {
            $session->set(SamlAuthenticator::LAST_REQUEST_ID, $lastRequestId);
        }
        $request->setSession($session);

        $idpResolver = $this->createConfiguredMock(IdpResolverInterface::class, [
            'resolve' => null,
        ]);

        $authRegistry = new AuthRegistry();
        $authRegistry->addService('foo', $auth($this));

        $authenticator = $this->createSamlAuthenticator(
            userProvider: $userProvider !== null ? $userProvider($this) : null,
            idpResolver: $idpResolver,
            authRegistry: $authRegistry,
            options: $options,
            samlUserFactory: $samlUserFactory !== null ? $samlUserFactory($this) : null,
            useProxyVars: $useProxyVars,
        );

        self::assertFalse(Utils::getProxyVars());
        $passport = $authenticator->authenticate($request);
        self::assertSame($expectedUseProxyVars, Utils::getProxyVars());
        self::assertSame($expectedUserIdentifier, $passport->getUser()->getUserIdentifier());

        /** @var SamlAttributesBadge $badge */
        $badge = $passport->getBadge(SamlAttributesBadge::class);
        self::assertSame($expectedSamlAttributes, $badge->getAttributes());

        if ($eventDispatcher === null) {
            return;
        }

        /** @var DeferredEventBadge $deferredEventBadge */
        $deferredEventBadge = $passport->getBadge(DeferredEventBadge::class);
        self::assertInstanceOf(DeferredEventBadge::class, $deferredEventBadge);

        /** @var Event $deferredEvent */
        $deferredEvent = $deferredEventBadge->getEvent();
        self::assertInstanceOf(Event::class, $deferredEvent);

        $eventDispatcher($this)->dispatch($deferredEvent);
    }

    public static function provideSuccessAuthenticateCases(): iterable
    {
        yield 'Not attribute friendly name + user identifier from OneLogin auth' => [
            'auth' => static function (TestCase $case): Auth {
                $settingsMock = $case->createMock(Settings::class);
                $settingsMock
                    ->method('getSecurityData')
                    ->willReturn([])
                ;
                $auth = $case->createConfiguredMock(Auth::class, [
                    'getAttributes' => [
                        'username' => 'tester',
                        'email' => 'tester@example.com',
                    ],
                    'getSessionIndex' => 'session_index',
                    'getSettings' => $settingsMock,
                    'getNameId' => 'tester_id',
                ]);
                $auth
                    ->expects($case->never())
                    ->method('getAttributesWithFriendlyName')
                ;
                $auth
                    ->method('processResponse')
                    ->with(null)
                ;

                return $auth;
            },
            'userProvider' => static function (TestCase $case): UserProviderInterface {
                $userProvider = $case->createMock(UserProviderInterface::class);
                $userProvider
                    ->method('loadUserByIdentifier')
                    ->with('tester_id')
                    ->willReturn(new TestUser('tester_id'))
                ;

                return $userProvider;
            },
            'samlUserFactory' => null,
            'eventDispatcher' => null,
            'options' => [
                'use_attribute_friendly_name' => false,
            ],
            'lastRequestId' => null,
            'useProxyVars' => false,
            'expectedUserIdentifier' => 'tester_id',
            'expectedSamlAttributes' => [
                'username' => 'tester',
                'email' => 'tester@example.com',
                SamlAuthenticator::SESSION_INDEX_ATTRIBUTE => 'session_index',
            ],
            'expectedUseProxyVars' => false,
        ];

        yield 'Attribute friendly name + user identifier from SAML attributes (array) + SamlUser created' => [
            'auth' => static function (TestCase $case): Auth {
                $settingsMock = $case->createMock(Settings::class);
                $settingsMock
                    ->method('getSecurityData')
                    ->willReturn(['rejectUnsolicitedResponsesWithInResponseTo' => false])
                ;
                $auth = $case->createConfiguredMock(Auth::class, [
                    'getAttributesWithFriendlyName' => [
                        'username' => ['tester_attribute'],
                        'email' => 'tester@example.com',
                    ],
                    'getSessionIndex' => 'session_index',
                    'getSettings' => $settingsMock,
                ]);
                $auth
                    ->expects($case->never())
                    ->method('getAttributes')
                ;
                $auth
                    ->expects($case->never())
                    ->method('getNameId')
                ;
                $auth
                    ->method('processResponse')
                    ->with(null)
                ;

                return $auth;
            },
            'userProvider' => static function (TestCase $case): UserProviderInterface {
                $userProvider = $case->createMock(UserProviderInterface::class);
                $userProvider
                    ->method('loadUserByIdentifier')
                    ->willThrowException(new UserNotFoundException())
                ;

                return $userProvider;
            },
            'samlUserFactory' => static function (TestCase $case): SamlUserFactoryInterface {
                $user = $case->createConfiguredMock(SamlUserInterface::class, [
                    'getUserIdentifier' => 'tester_attribute',
                ]);
                $user
                    ->expects($case->never())
                    ->method('setSamlAttributes')
                ;

                $samlUserFactory = $case->createMock(SamlUserFactoryInterface::class);
                $samlUserFactory
                    ->method('createUser')
                    ->with('tester_attribute', [
                        'username' => ['tester_attribute'],
                        'email' => 'tester@example.com',
                        SamlAuthenticator::SESSION_INDEX_ATTRIBUTE => 'session_index',
                    ])
                    ->willReturn($user)
                ;

                return $samlUserFactory;
            },
            'eventDispatcher' => static function (TestCase $case): EventDispatcherInterface {
                $eventDispatcher = $case->createMock(EventDispatcherInterface::class);
                $eventDispatcher
                    ->expects($case->once())
                    ->method('dispatch')
                    ->with(self::isInstanceOf(UserCreatedEvent::class))
                ;

                return $eventDispatcher;
            },
            'options' => [
                'use_attribute_friendly_name' => true,
                'identifier_attribute' => 'username',
            ],
            'lastRequestId' => null,
            'useProxyVars' => false,
            'expectedUserIdentifier' => 'tester_attribute',
            'expectedSamlAttributes' => [
                'username' => ['tester_attribute'],
                'email' => 'tester@example.com',
                SamlAuthenticator::SESSION_INDEX_ATTRIBUTE => 'session_index',
            ],
            'expectedUseProxyVars' => false,
        ];

        yield 'Attribute friendly name + user identifier from SAML attributes (string) + SamlUser modified + InResponseTo' => [
            'auth' => static function (TestCase $case): Auth {
                $settingsMock = $case->createMock(Settings::class);
                $settingsMock
                    ->method('getSecurityData')
                    ->willReturn(['rejectUnsolicitedResponsesWithInResponseTo' => true])
                ;
                $auth = $case->createConfiguredMock(Auth::class, [
                    'getAttributesWithFriendlyName' => [
                        'username' => 'tester_attribute',
                        'email' => 'tester@example.com',
                    ],
                    'getSessionIndex' => 'session_index',
                    'getSettings' => $settingsMock,
                ]);
                $auth
                    ->expects($case->never())
                    ->method('getAttributes')
                ;
                $auth
                    ->expects($case->never())
                    ->method('getNameId')
                ;
                $auth
                    ->method('processResponse')
                    ->with('requestID')
                ;

                return $auth;
            },
            'userProvider' => static function (TestCase $case): UserProviderInterface {
                $user = $case->createConfiguredMock(SamlUserInterface::class, [
                    'getUserIdentifier' => 'tester_attribute',
                ]);
                $user
                    ->method('setSamlAttributes')
                    ->with([
                        'username' => 'tester_attribute',
                        'email' => 'tester@example.com',
                        SamlAuthenticator::SESSION_INDEX_ATTRIBUTE => 'session_index',
                    ])
                ;

                $userProvider = $case->createMock(UserProviderInterface::class);
                $userProvider
                    ->method('loadUserByIdentifier')
                    ->with('tester_attribute')
                    ->willReturn($user)
                ;

                return $userProvider;
            },
            'samlUserFactory' => null,
            'eventDispatcher' => static function (TestCase $case): EventDispatcherInterface {
                $eventDispatcher = $case->createMock(EventDispatcherInterface::class);
                $eventDispatcher
                    ->expects($case->once())
                    ->method('dispatch')
                    ->with(self::isInstanceOf(UserModifiedEvent::class))
                ;

                return $eventDispatcher;
            },
            'options' => [
                'use_attribute_friendly_name' => true,
                'identifier_attribute' => 'username',
            ],
            'lastRequestId' => 'requestID',
            'useProxyVars' => true,
            'expectedUserIdentifier' => 'tester_attribute',
            'expectedSamlAttributes' => [
                'username' => 'tester_attribute',
                'email' => 'tester@example.com',
                SamlAuthenticator::SESSION_INDEX_ATTRIBUTE => 'session_index',
            ],
            'expectedUseProxyVars' => true,
        ];
    }

    /**
     * @param callable(TestCase): Auth                      $auth
     * @param ?callable(TestCase): UserProviderInterface    $userProvider
     * @param ?callable(TestCase): SamlUserFactoryInterface $samlUserFactory
     * @param class-string<\Throwable>                      $expectedException
     */
    #[DataProvider('provideAuthenticateExceptionCases')]
    public function testAuthenticateException(
        callable $auth,
        ?callable $userProvider,
        ?callable $samlUserFactory,
        array $options,
        string $expectedException,
        ?string $expectedMessage,
    ): void {
        $request = Request::create('/');
        $request->setSession(new Session(new MockArraySessionStorage()));

        $idpResolver = $this->createConfiguredMock(IdpResolverInterface::class, [
            'resolve' => null,
        ]);

        $authRegistry = new AuthRegistry();
        $authRegistry->addService('foo', $auth($this));

        $authenticator = $this->createSamlAuthenticator(
            userProvider: $userProvider !== null ? $userProvider($this) : null,
            idpResolver: $idpResolver,
            authRegistry: $authRegistry,
            options: $options,
            samlUserFactory: $samlUserFactory !== null ? $samlUserFactory($this) : null,
        );

        $this->expectException($expectedException);
        if ($expectedMessage !== null) {
            $this->expectExceptionMessage($expectedMessage);
        }

        $authenticator->authenticate($request)->getUser();
    }

    public static function provideAuthenticateExceptionCases(): iterable
    {
        yield 'SAML attributes without identifier attribute' => [
            'auth' => static function (TestCase $case): Auth {
                $settingsMock = $case->createMock(Settings::class);
                $settingsMock
                    ->method('getSecurityData')
                    ->willReturn([])
                ;
                $auth = $case->createConfiguredMock(Auth::class, [
                    'getAttributes' => [],
                    'getSessionIndex' => 'session_index',
                    'getSettings' => $settingsMock,
                ]);
                $auth
                    ->expects($case->never())
                    ->method('getNameId')
                ;

                return $auth;
            },
            'userProvider' => null,
            'samlUserFactory' => null,
            'options' => [
                'identifier_attribute' => 'username',
            ],
            'expectedException' => \RuntimeException::class,
            'expectedMessage' => 'Attribute "username" not found in SAML data.',
        ];

        yield 'SAML attributes with invalid identifier attribute' => [
            'auth' => static function (TestCase $case): Auth {
                $settingsMock = $case->createMock(Settings::class);
                $settingsMock
                    ->method('getSecurityData')
                    ->willReturn([])
                ;
                $auth = $case->createConfiguredMock(Auth::class, [
                    'getAttributes' => [
                        'username' => [],
                    ],
                    'getSessionIndex' => 'session_index',
                    'getSettings' => $settingsMock,
                ]);
                $auth
                    ->expects($case->never())
                    ->method('getNameId')
                ;

                return $auth;
            },
            'userProvider' => null,
            'samlUserFactory' => null,
            'options' => [
                'identifier_attribute' => 'username',
            ],
            'expectedException' => \RuntimeException::class,
            'expectedMessage' => 'Attribute "username" does not contain valid user identifier.',
        ];

        yield 'User not found without SAML user factory' => [
            'auth' => static function (TestCase $case): Auth {
                $settingsMock = $case->createMock(Settings::class);
                $settingsMock
                    ->method('getSecurityData')
                    ->willReturn([])
                ;
                $auth = $case->createConfiguredMock(Auth::class, [
                    'getAttributes' => [],
                    'getSessionIndex' => 'session_index',
                    'getSettings' => $settingsMock,
                    'getNameId' => 'tester_id',
                ]);
                $auth
                    ->expects($case->never())
                    ->method('getAttributesWithFriendlyName')
                ;

                return $auth;
            },
            'userProvider' => static function (TestCase $case): UserProviderInterface {
                $userProvider = $case->createMock(UserProviderInterface::class);
                $userProvider
                    ->method('loadUserByIdentifier')
                    ->willThrowException(new UserNotFoundException())
                ;

                return $userProvider;
            },
            'samlUserFactory' => null,
            'options' => [],
            'expectedException' => UserNotFoundException::class,
            'expectedMessage' => null,
        ];

        yield 'User not found + SAML user factory exception' => [
            'auth' => static function (TestCase $case): Auth {
                $settingsMock = $case->createMock(Settings::class);
                $settingsMock
                    ->method('getSecurityData')
                    ->willReturn([])
                ;
                $auth = $case->createConfiguredMock(Auth::class, [
                    'getAttributes' => [],
                    'getSessionIndex' => 'session_index',
                    'getSettings' => $settingsMock,
                    'getNameId' => 'tester_id',
                ]);
                $auth
                    ->expects($case->never())
                    ->method('getAttributesWithFriendlyName')
                ;

                return $auth;
            },
            'userProvider' => static function (TestCase $case): UserProviderInterface {
                $userProvider = $case->createMock(UserProviderInterface::class);
                $userProvider
                    ->method('loadUserByIdentifier')
                    ->willThrowException(new UserNotFoundException())
                ;

                return $userProvider;
            },
            'samlUserFactory' => static function (TestCase $case): SamlUserFactoryInterface {
                $samlUserFactory = $case->createMock(SamlUserFactoryInterface::class);
                $samlUserFactory
                    ->method('createUser')
                    ->willThrowException(new \Exception())
                ;

                return $samlUserFactory;
            },
            'options' => [],
            'expectedException' => AuthenticationException::class,
            'expectedMessage' => 'The authentication failed.',
        ];
    }

    public function testCreateToken(): void
    {
        $authenticator = $this->createSamlAuthenticator();
        $passport = new SelfValidatingPassport(
            new UserBadge('tester', static fn (): TestUser => new TestUser('tester', ['ROLE_EXTRA_USER'])),
            [new SamlAttributesBadge(['username' => 'tester'])],
        );

        /** @var \Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken $token */
        $token = $authenticator->createToken($passport, 'fwname');

        self::assertSame('tester', $token->getUserIdentifier());
        self::assertSame(['ROLE_EXTRA_USER'], $token->getRoleNames());
        self::assertSame('fwname', $token->getFirewallName());
        self::assertSame(['username' => 'tester'], $token->getAttributes());
    }

    public function testCreateTokenWithoutSamlAttributesBadgeException(): void
    {
        $authenticator = $this->createSamlAuthenticator();
        $passport = new SelfValidatingPassport(new UserBadge('tester'));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Passport should contains a "Nbgrp\OneloginSamlBundle\Security\Http\Authenticator\Passport\Badge\SamlAttributesBadge" badge.');

        $authenticator->createToken($passport, 'foo');
    }

    public function testOnAuthenticationSuccess(): void
    {
        $request = self::createStub(Request::class);
        $token = self::createStub(TokenInterface::class);

        $authenticationSuccessHandler = $this->createMock(AuthenticationSuccessHandlerInterface::class);
        $authenticationSuccessHandler
            ->expects(self::once())
            ->method('onAuthenticationSuccess')
            ->with($request, $token)
        ;

        $authenticator = $this->createSamlAuthenticator(
            authenticationSuccessHandler: $authenticationSuccessHandler,
        );

        $authenticator->onAuthenticationSuccess($request, $token, 'foo');
    }

    public function testOnAuthenticationFailure(): void
    {
        $request = self::createStub(Request::class);
        $exception = new AuthenticationException();

        $authenticationFailureHandler = $this->createMock(AuthenticationFailureHandlerInterface::class);
        $authenticationFailureHandler
            ->expects(self::once())
            ->method('onAuthenticationFailure')
            ->with($request, $exception)
        ;

        $authenticator = $this->createSamlAuthenticator(
            authenticationFailureHandler: $authenticationFailureHandler,
        );

        $authenticator->onAuthenticationFailure($request, $exception);
    }

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
        bool $useProxyVars = false,
    ): SamlAuthenticator {
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
            $useProxyVars,
        );
    }
}
