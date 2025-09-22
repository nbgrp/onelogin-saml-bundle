<?php
// SPDX-License-Identifier: BSD-3-Clause

declare(strict_types=1);

namespace Nbgrp\Tests\OneloginSamlBundle\EventListener\Security;

use Nbgrp\OneloginSamlBundle\EventListener\Security\SamlLogoutListener;
use Nbgrp\OneloginSamlBundle\Idp\IdpResolver;
use Nbgrp\OneloginSamlBundle\Idp\IdpResolverInterface;
use Nbgrp\OneloginSamlBundle\Onelogin\AuthRegistry;
use Nbgrp\OneloginSamlBundle\Onelogin\AuthRegistryInterface;
use Nbgrp\OneloginSamlBundle\Security\Http\Authenticator\SamlAuthenticator;
use Nbgrp\OneloginSamlBundle\Security\Http\Authenticator\Token\SamlToken;
use Nbgrp\Tests\OneloginSamlBundle\TestUser;
use OneLogin\Saml2\Auth;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * @internal
 */
#[CoversClass(SamlLogoutListener::class)]
final class SamlLogoutListenerTest extends TestCase
{
    /**
     * @param callable(TestCase): AuthRegistryInterface $authRegistry
     */
    #[DataProvider('provideCases')]
    public function test(callable $authRegistry, IdpResolverInterface $ipdResolver, Request $request, ?TokenInterface $token): void
    {
        $event = $this->createMock(LogoutEvent::class);
        $event
            ->method('getRequest')
            ->willReturn($request)
        ;
        $event
            ->expects(self::once())
            ->method('getToken')
            ->willReturn($token)
        ;

        (new SamlLogoutListener($authRegistry($this), $ipdResolver))->processSingleLogout($event);
    }

    public static function provideCases(): iterable
    {
        yield 'No Auth service' => [
            'authRegistry' => static function (TestCase $case): AuthRegistryInterface {
                $auth = $case->createMock(Auth::class);
                $auth
                    ->expects($case->never())
                    ->method('processSLO')
                ;

                $authRegistry = new AuthRegistry();
                $authRegistry->addService('foo', $auth);

                return $authRegistry;
            },
            'ipdResolver' => new IdpResolver('idp'),
            'request' => Request::create('/logout', 'GET', ['idp' => 'unknown']),
            'token' => null,
        ];

        yield 'Custom Auth service without SAML token' => [
            'authRegistry' => static function (TestCase $case): AuthRegistryInterface {
                $auth = $case->createMock(Auth::class);
                $auth
                    ->expects($case->never())
                    ->method('processSLO')
                ;

                $authRegistry = new AuthRegistry();
                $authRegistry->addService('foo', $auth);

                return $authRegistry;
            },
            'ipdResolver' => new IdpResolver('idp'),
            'request' => Request::create('/logout', 'GET', ['idp' => 'foo']),
            'token' => self::createStub(TokenInterface::class),
        ];

        yield 'Logout without session index' => [
            'authRegistry' => static function (TestCase $case): AuthRegistryInterface {
                $auth = $case->createMock(Auth::class);
                $auth
                    ->method('processSLO')
                    ->willThrowException(new \OneLogin\Saml2\Error('error'))
                ;
                $auth
                    ->method('getSLOurl')
                    ->willReturn('some_slo_url')
                ;
                $auth
                    ->method('logout')
                    ->with(null, [], 'tester', null)
                ;

                $authRegistry = new AuthRegistry();
                $authRegistry->addService('foo', $auth);

                return $authRegistry;
            },
            'ipdResolver' => new IdpResolver('idp'),
            'request' => Request::create('/logout'),
            'token' => new SamlToken(new TestUser('tester'), 'foo', [], []),
        ];

        yield 'Logout with session index' => [
            'authRegistry' => static function (TestCase $case): AuthRegistryInterface {
                $auth = $case->createMock(Auth::class);
                $auth
                    ->method('processSLO')
                    ->willThrowException(new \OneLogin\Saml2\Error('error'))
                ;
                $auth
                    ->method('getSLOurl')
                    ->willReturn('some_slo_url')
                ;
                $auth
                    ->method('logout')
                    ->with(null, [], 'tester', 'session_index')
                ;

                $authRegistry = new AuthRegistry();
                $authRegistry->addService('foo', $auth);

                return $authRegistry;
            },
            'ipdResolver' => new IdpResolver('idp'),
            'request' => Request::create('/logout'),
            'token' => new SamlToken(new TestUser('tester'), 'foo', [], [SamlAuthenticator::SESSION_INDEX_ATTRIBUTE => 'session_index']),
        ];
    }
}
