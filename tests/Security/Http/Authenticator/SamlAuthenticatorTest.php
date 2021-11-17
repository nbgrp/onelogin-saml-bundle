<?php declare(strict_types=1);
// SPDX-License-Identifier: BSD-3-Clause

namespace Nbgrp\Tests\OneloginSamlBundle\Security\Http\Authenticator;

use Nbgrp\OneloginSamlBundle\Event\UserCreatedEvent;
use Nbgrp\OneloginSamlBundle\Event\UserModifiedEvent;
use Nbgrp\OneloginSamlBundle\Idp\IdpResolver;
use Nbgrp\OneloginSamlBundle\Idp\IdpResolverInterface;
use Nbgrp\OneloginSamlBundle\Onelogin\AuthRegistry;
use Nbgrp\OneloginSamlBundle\Onelogin\AuthRegistryInterface;
use Nbgrp\OneloginSamlBundle\Security\Http\Authenticator\Passport\Badge\SamlAttributesBadge;
use Nbgrp\OneloginSamlBundle\Security\Http\Authenticator\SamlAuthenticator;
use Nbgrp\OneloginSamlBundle\Security\User\SamlUserFactoryInterface;
use Nbgrp\OneloginSamlBundle\Security\User\SamlUserInterface;
use Nbgrp\Tests\OneloginSamlBundle\TestUser;
use OneLogin\Saml2\Auth;
use OneLogin\Saml2\Settings;
use PHPUnit\Framework\Constraint\IsInstanceOf;
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
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @covers \Nbgrp\OneloginSamlBundle\Security\Http\Authenticator\SamlAuthenticator
 *
 * @internal
 */
final class SamlAuthenticatorTest extends TestCase
{
    /**
     * @dataProvider supportsProvider
     */
    public function testSupports(Request $request, bool $expectedSupports): void
    {
        $authenticator = $this->createSamlAuthenticator(
            httpUtils: new HttpUtils(),
            options: ['check_path' => '/check'],
        );

        self::assertSame($expectedSupports, $authenticator->supports($request));
    }

    public function supportsProvider(): \Generator
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

    /**
     * @dataProvider startProvider
     */
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

    public function startProvider(): \Generator
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

    /**
     * @dataProvider authenticateSessionExceptionProvider
     */
    public function testAuthenticateSessionException(Request $request, string $expectedMessage): void
    {
        $authenticator = $this->createSamlAuthenticator(
            options: ['require_previous_session' => true],
        );

        $this->expectException(SessionUnavailableException::class);
        $this->expectExceptionMessage($expectedMessage);
        $authenticator->authenticate($request);
    }

    public function authenticateSessionExceptionProvider(): \Generator
    {
        yield 'No session' => [
            'request' => Request::create('/'),
            'expectedMessage' => 'This authentication method requires a session.',
        ];

        $request = Request::create('/');
        $request->setSession(new Session(new MockArraySessionStorage()));
        yield 'No cookies' => [
            'request' => $request,
            'expectedMessage' => 'Your session has timed out, or you have disabled cookies.',
        ];
    }

    /**
     * @dataProvider authenticateOneLoginErrorsExceptionProvider
     */
    public function testAuthenticateOneLoginErrorsException(IdpResolverInterface $idpResolver, AuthRegistryInterface $authRegistry, string $expectedMessage): void
    {
        $request = Request::create('/');
        $request->setSession(new Session(new MockArraySessionStorage()));

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->method('error')
            ->with($expectedMessage)
        ;

        $authenticator = $this->createSamlAuthenticator(
            idpResolver: $idpResolver,
            authRegistry: $authRegistry,
            options: ['require_previous_session' => false],
            logger: $logger,
        );

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage($expectedMessage);
        $authenticator->authenticate($request);
    }

    public function authenticateOneLoginErrorsExceptionProvider(): \Generator
    {
        yield 'Default Auth service + OneLogin auth error' => (function (): array {
            $idpResolver = $this->createConfiguredMock(IdpResolverInterface::class, [
                'resolve' => null,
            ]);
            $auth = $this->createConfiguredMock(Auth::class, [
                'getErrors' => ['invalid something'],
                'getLastErrorReason' => 'error reason',
            ]);
            $auth
                ->expects(self::once())
                ->method('processResponse')
            ;
            $settingsMock = $this->createMock(Settings::class);
            $settingsMock
                ->method('getSecurityData')
                ->willReturn([])
            ;
            $auth
                ->expects(self::once())
                ->method('getSettings')
                ->willReturn($settingsMock)
            ;
            $authRegistry = new AuthRegistry();
            $authRegistry->addService('foo', $auth);

            return [
                'idpResolver' => $idpResolver,
                'authRegistry' => $authRegistry,
                'expectedMessage' => 'error reason',
            ];
        })();

        yield 'Custom Auth service + undefined OneLogin auth error' => (function (): array {
            $idpResolver = $this->createConfiguredMock(IdpResolverInterface::class, [
                'resolve' => 'custom',
            ]);
            $auth = $this->createConfiguredMock(Auth::class, [
                'getErrors' => ['invalid something'],
                'getLastErrorReason' => null,
            ]);
            $auth
                ->expects(self::once())
                ->method('processResponse')
            ;
            $settingsMock = $this->createMock(Settings::class);
            $settingsMock
                ->method('getSecurityData')
                ->willReturn([])
            ;
            $auth
                ->expects(self::once())
                ->method('getSettings')
                ->willReturn($settingsMock)
            ;
            $authRegistry = new AuthRegistry();
            $authRegistry->addService('custom', $auth);

            return [
                'idpResolver' => $idpResolver,
                'authRegistry' => $authRegistry,
                'expectedMessage' => 'Undefined OneLogin auth error.',
            ];
        })();
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
            options: ['require_previous_session' => false],
        );

        $this->expectException(AuthenticationServiceException::class);
        $this->expectExceptionMessage('There is no configured Auth services.');
        $authenticator->authenticate($request);
    }

    /**
     * @dataProvider successAuthenticateProvider
     */
    public function testSuccessAuthenticate(Auth $auth, ?UserProviderInterface $userProvider, ?SamlUserFactoryInterface $samlUserFactory, ?EventDispatcherInterface $eventDispatcher, array $options, ?string $lastRequestId, string $expectedUserIdentifier, array $expectedSamlAttributes): void
    {
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
        $authRegistry->addService('foo', $auth);

        $authenticator = $this->createSamlAuthenticator(
            userProvider: $userProvider,
            idpResolver: $idpResolver,
            authRegistry: $authRegistry,
            options: $options,
            samlUserFactory: $samlUserFactory,
            eventDispatcher: $eventDispatcher,
        );

        $passport = $authenticator->authenticate($request);

        self::assertSame($expectedUserIdentifier, $passport->getUser()->getUserIdentifier());

        /** @var SamlAttributesBadge $badge */
        $badge = $passport->getBadge(SamlAttributesBadge::class);
        self::assertSame($expectedSamlAttributes, $badge->getAttributes());
    }

    public function successAuthenticateProvider(): \Generator
    {
        yield 'Not attribute friendly name + user identifier from OneLogin auth' => (function (): array {
            $settingsMock = $this->createMock(Settings::class);
            $settingsMock
                ->method('getSecurityData')
                ->willReturn([])
            ;
            $auth = $this->createConfiguredMock(Auth::class, [
                'getAttributes' => [
                    'username' => 'tester',
                    'email' => 'tester@example.com',
                ],
                'getSessionIndex' => 'session_index',
                'getSettings' => $settingsMock,
                'getNameId' => 'tester_id',
            ]);
            $auth
                ->expects(self::never())
                ->method('getAttributesWithFriendlyName')
            ;
            $auth
                ->method('processResponse')
                ->with(null)
            ;

            $userProvider = $this->createMock(UserProviderInterface::class);
            $userProvider
                ->method('loadUserByIdentifier')
                ->with('tester_id')
                ->willReturn(new TestUser('tester_id'))
            ;

            return [
                'auth' => $auth,
                'userProvider' => $userProvider,
                'samlUserFactory' => null,
                'eventDispatcher' => null,
                'options' => [
                    'require_previous_session' => false,
                    'use_attribute_friendly_name' => false,
                ],
                'lastRequestId' => null,
                'expectedUserIdentifier' => 'tester_id',
                'expectedSamlAttributes' => [
                    'username' => 'tester',
                    'email' => 'tester@example.com',
                    SamlAuthenticator::SESSION_INDEX_ATTRIBUTE => 'session_index',
                ],
            ];
        })();

        yield 'Attribute friendly name + user identifier from SAML attributes (array) + SamlUser created' => (function (): array {
            $settingsMock = $this->createMock(Settings::class);
            $settingsMock
                ->method('getSecurityData')
                ->willReturn(['rejectUnsolicitedResponsesWithInResponseTo' => false])
            ;
            $auth = $this->createConfiguredMock(Auth::class, [
                'getAttributesWithFriendlyName' => [
                    'username' => ['tester_attribute'],
                    'email' => 'tester@example.com',
                ],
                'getSessionIndex' => 'session_index',
                'getSettings' => $settingsMock,
            ]);
            $auth
                ->expects(self::never())
                ->method('getAttributes')
            ;
            $auth
                ->expects(self::never())
                ->method('getNameId')
            ;
            $auth
                ->method('processResponse')
                ->with(null)
            ;

            $userProvider = $this->createMock(UserProviderInterface::class);
            $userProvider
                ->method('loadUserByIdentifier')
                ->willThrowException(new UserNotFoundException())
            ;

            $user = $this->createConfiguredMock(SamlUserInterface::class, [
                'getUserIdentifier' => 'tester_attribute',
            ]);
            $user
                ->expects(self::never())
                ->method('setSamlAttributes')
            ;

            $samlUserFactory = $this->createMock(SamlUserFactoryInterface::class);
            $samlUserFactory
                ->method('createUser')
                ->with('tester_attribute', [
                    'username' => ['tester_attribute'],
                    'email' => 'tester@example.com',
                    SamlAuthenticator::SESSION_INDEX_ATTRIBUTE => 'session_index',
                ])
                ->willReturn($user)
            ;

            $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
            $eventDispatcher
                ->expects(self::once())
                ->method('dispatch')
                ->with(new IsInstanceOf(UserCreatedEvent::class))
            ;

            return [
                'auth' => $auth,
                'userProvider' => $userProvider,
                'samlUserFactory' => $samlUserFactory,
                'eventDispatcher' => $eventDispatcher,
                'options' => [
                    'require_previous_session' => false,
                    'use_attribute_friendly_name' => true,
                    'identifier_attribute' => 'username',
                ],
                'lastRequestId' => null,
                'expectedUserIdentifier' => 'tester_attribute',
                'expectedSamlAttributes' => [
                    'username' => ['tester_attribute'],
                    'email' => 'tester@example.com',
                    SamlAuthenticator::SESSION_INDEX_ATTRIBUTE => 'session_index',
                ],
            ];
        })();

        yield 'Attribute friendly name + user identifier from SAML attributes (string) + SamlUser modified + InResponseTo' => (function (): array {
            $settingsMock = $this->createMock(Settings::class);
            $settingsMock
                ->method('getSecurityData')
                ->willReturn(['rejectUnsolicitedResponsesWithInResponseTo' => true])
            ;
            $auth = $this->createConfiguredMock(Auth::class, [
                'getAttributesWithFriendlyName' => [
                    'username' => 'tester_attribute',
                    'email' => 'tester@example.com',
                ],
                'getSessionIndex' => 'session_index',
                'getSettings' => $settingsMock,
            ]);
            $auth
                ->expects(self::never())
                ->method('getAttributes')
            ;
            $auth
                ->expects(self::never())
                ->method('getNameId')
            ;
            $auth
                ->method('processResponse')
                ->with('requestID')
            ;

            $user = $this->createConfiguredMock(SamlUserInterface::class, [
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

            $userProvider = $this->createMock(UserProviderInterface::class);
            $userProvider
                ->method('loadUserByIdentifier')
                ->with('tester_attribute')
                ->willReturn($user)
            ;

            $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
            $eventDispatcher
                ->expects(self::once())
                ->method('dispatch')
                ->with(new IsInstanceOf(UserModifiedEvent::class))
            ;

            return [
                'auth' => $auth,
                'userProvider' => $userProvider,
                'samlUserFactory' => null,
                'eventDispatcher' => $eventDispatcher,
                'options' => [
                    'require_previous_session' => false,
                    'use_attribute_friendly_name' => true,
                    'identifier_attribute' => 'username',
                ],
                'lastRequestId' => 'requestID',
                'expectedUserIdentifier' => 'tester_attribute',
                'expectedSamlAttributes' => [
                    'username' => 'tester_attribute',
                    'email' => 'tester@example.com',
                    SamlAuthenticator::SESSION_INDEX_ATTRIBUTE => 'session_index',
                ],
            ];
        })();
    }

    /**
     * @dataProvider authenticateExceptionProvider
     *
     * @param class-string<\Throwable> $expectedException
     */
    public function testAuthenticateException(Auth $auth, ?UserProviderInterface $userProvider, ?SamlUserFactoryInterface $samlUserFactory, array $options, string $expectedException, ?string $expectedMessage): void
    {
        $request = Request::create('/');
        $request->setSession(new Session(new MockArraySessionStorage()));

        $idpResolver = $this->createConfiguredMock(IdpResolverInterface::class, [
            'resolve' => null,
        ]);

        $authRegistry = new AuthRegistry();
        $authRegistry->addService('foo', $auth);

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

    public function authenticateExceptionProvider(): \Generator
    {
        yield 'SAML attributes without identifier attribute' => (function (): array {
            $settingsMock = $this->createMock(Settings::class);
            $settingsMock
                ->method('getSecurityData')
                ->willReturn([])
            ;
            $auth = $this->createConfiguredMock(Auth::class, [
                'getAttributes' => [],
                'getSessionIndex' => 'session_index',
                'getSettings' => $settingsMock,
            ]);
            $auth
                ->expects(self::never())
                ->method('getNameId')
            ;

            return [
                'auth' => $auth,
                'userProvider' => null,
                'samlUserFactory' => null,
                'options' => [
                    'identifier_attribute' => 'username',
                    'require_previous_session' => false,
                ],
                'expectedException' => \RuntimeException::class,
                'expectedMessage' => 'Attribute "username" not found in SAML data.',
            ];
        })();

        yield 'SAML attributes with invalid identifier attribute' => (function (): array {
            $settingsMock = $this->createMock(Settings::class);
            $settingsMock
                ->method('getSecurityData')
                ->willReturn([])
            ;
            $auth = $this->createConfiguredMock(Auth::class, [
                'getAttributes' => [
                    'username' => [],
                ],
                'getSessionIndex' => 'session_index',
                'getSettings' => $settingsMock,
            ]);
            $auth
                ->expects(self::never())
                ->method('getNameId')
            ;

            return [
                'auth' => $auth,
                'userProvider' => null,
                'samlUserFactory' => null,
                'options' => [
                    'identifier_attribute' => 'username',
                    'require_previous_session' => false,
                ],
                'expectedException' => \RuntimeException::class,
                'expectedMessage' => 'Attribute "username" does not contain valid user identifier.',
            ];
        })();

        yield 'User not found without SAML user factory' => (function (): array {
            $settingsMock = $this->createMock(Settings::class);
            $settingsMock
                ->method('getSecurityData')
                ->willReturn([])
            ;
            $auth = $this->createConfiguredMock(Auth::class, [
                'getAttributes' => [],
                'getSessionIndex' => 'session_index',
                'getSettings' => $settingsMock,
                'getNameId' => 'tester_id',
            ]);
            $auth
                ->expects(self::never())
                ->method('getAttributesWithFriendlyName')
            ;

            $userProvider = $this->createMock(UserProviderInterface::class);
            $userProvider
                ->method('loadUserByIdentifier')
                ->willThrowException(new UserNotFoundException())
            ;

            return [
                'auth' => $auth,
                'userProvider' => $userProvider,
                'samlUserFactory' => null,
                'options' => [
                    'require_previous_session' => false,
                ],
                'expectedException' => UserNotFoundException::class,
                'expectedMessage' => null,
            ];
        })();

        yield 'User not found + SAML user factory exception' => (function (): array {
            $settingsMock = $this->createMock(Settings::class);
            $settingsMock
                ->method('getSecurityData')
                ->willReturn([])
            ;
            $auth = $this->createConfiguredMock(Auth::class, [
                'getAttributes' => [],
                'getSessionIndex' => 'session_index',
                'getSettings' => $settingsMock,
                'getNameId' => 'tester_id',
            ]);
            $auth
                ->expects(self::never())
                ->method('getAttributesWithFriendlyName')
            ;

            $userProvider = $this->createMock(UserProviderInterface::class);
            $userProvider
                ->method('loadUserByIdentifier')
                ->willThrowException(new UserNotFoundException())
            ;

            $samlUserFactory = $this->createMock(SamlUserFactoryInterface::class);
            $samlUserFactory
                ->method('createUser')
                ->willThrowException(new \Exception())
            ;

            return [
                'auth' => $auth,
                'userProvider' => $userProvider,
                'samlUserFactory' => $samlUserFactory,
                'options' => [
                    'require_previous_session' => false,
                ],
                'expectedException' => AuthenticationException::class,
                'expectedMessage' => 'The authentication failed.',
            ];
        })();
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
        $request = $this->createStub(Request::class);
        $token = $this->createStub(TokenInterface::class);

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
        $request = $this->createStub(Request::class);
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
        ?EventDispatcherInterface $eventDispatcher = null,
        ?LoggerInterface $logger = null,
        string $idpParameterName = 'idp',
    ): SamlAuthenticator {
        return new SamlAuthenticator(
            $httpUtils ?? $this->createStub(HttpUtils::class),
            $userProvider ?? $this->createStub(UserProviderInterface::class),
            $idpResolver ?? $this->createStub(IdpResolverInterface::class),
            $authRegistry ?? $this->createStub(AuthRegistryInterface::class),
            $authenticationSuccessHandler ?? $this->createStub(AuthenticationSuccessHandlerInterface::class),
            $authenticationFailureHandler ?? $this->createStub(AuthenticationFailureHandlerInterface::class),
            $options,
            $samlUserFactory,
            $eventDispatcher,
            $logger,
            $idpParameterName,
        );
    }
}
