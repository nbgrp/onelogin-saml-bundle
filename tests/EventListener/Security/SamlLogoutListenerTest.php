<?php
// SPDX-License-Identifier: BSD-3-Clause

declare(strict_types=1);

namespace Nbgrp\Tests\OneloginSamlBundle\EventListener\Security;

use Nbgrp\OneloginSamlBundle\EventListener\Security\SamlLogoutListener;
use Nbgrp\OneloginSamlBundle\Idp\IdpResolver;
use Nbgrp\OneloginSamlBundle\Idp\IdpResolverInterface;
use Nbgrp\OneloginSamlBundle\Onelogin\AuthRegistry;
use Nbgrp\OneloginSamlBundle\Security\Http\Authenticator\SamlAuthenticator;
use Nbgrp\OneloginSamlBundle\Security\Http\Authenticator\Token\SamlToken;
use Nbgrp\Tests\OneloginSamlBundle\TestUser;
use OneLogin\Saml2\Auth;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * @covers \Nbgrp\OneloginSamlBundle\EventListener\Security\SamlLogoutListener
 *
 * @internal
 */
final class SamlLogoutListenerTest extends TestCase
{
    /**
     * @dataProvider provideCases
     */
    public function test(AuthRegistry $authRegistry, IdpResolverInterface $ipdResolver, Request $request, ?TokenInterface $token): void
    {
        $event = $this->createMock(LogoutEvent::class);
        $event
            ->method('getRequest')
            ->willReturn($request)
        ;
        $event
            ->expects($token ? self::once() : self::never())
            ->method('getToken')
            ->willReturn($token)
        ;

        (new SamlLogoutListener($authRegistry, $ipdResolver))->processSingleLogout($event);
    }

    public function provideCases(): iterable
    {
        yield 'No Auth service' => [
            'authRegistry' => (function (): AuthRegistry {
                $auth = $this->createMock(Auth::class);
                $auth
                    ->expects(self::never())
                    ->method('processSLO')
                ;

                $authRegistry = new AuthRegistry();
                $authRegistry->addService('foo', $auth);

                return $authRegistry;
            })(),
            'ipdResolver' => new IdpResolver('idp'),
            'request' => Request::create('/logout', 'GET', ['idp' => 'unknown']),
            'token' => null,
        ];

        yield 'Custom Auth service without SAML token' => [
            'authRegistry' => (function (): AuthRegistry {
                $auth = $this->createMock(Auth::class);
                $auth
                    ->expects(self::never())
                    ->method('processSLO')
                ;

                $authRegistry = new AuthRegistry();
                $authRegistry->addService('foo', $auth);

                return $authRegistry;
            })(),
            'ipdResolver' => new IdpResolver('idp'),
            'request' => Request::create('/logout', 'GET', ['idp' => 'foo']),
            'token' => $this->createStub(TokenInterface::class),
        ];

        yield 'Logout without session index' => [
            'authRegistry' => (function (): AuthRegistry {
                $auth = $this->createMock(Auth::class);
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
            })(),
            'ipdResolver' => new IdpResolver('idp'),
            'request' => Request::create('/logout'),
            'token' => new SamlToken(new TestUser('tester'), 'foo', [], []),
        ];

        yield 'Logout with session index' => [
            'authRegistry' => (function (): AuthRegistry {
                $auth = $this->createMock(Auth::class);
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
            })(),
            'ipdResolver' => new IdpResolver('idp'),
            'request' => Request::create('/logout'),
            'token' => new SamlToken(new TestUser('tester'), 'foo', [], [SamlAuthenticator::SESSION_INDEX_ATTRIBUTE => 'session_index']),
        ];
    }
}
