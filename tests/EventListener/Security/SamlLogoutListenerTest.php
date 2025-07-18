<?php
// SPDX-License-Identifier: BSD-3-Clause

declare(strict_types=1);

namespace Nbgrp\Tests\OneloginSamlBundle\EventListener\Security;

use Nbgrp\OneloginSamlBundle\EventListener\Security\SamlLogoutListener;
use Nbgrp\OneloginSamlBundle\Idp\IdpResolver;
use Nbgrp\OneloginSamlBundle\Onelogin\AuthRegistry;
use Nbgrp\OneloginSamlBundle\Security\Http\Authenticator\SamlAuthenticator;
use Nbgrp\OneloginSamlBundle\Security\Http\Authenticator\Token\SamlToken;
use Nbgrp\Tests\OneloginSamlBundle\TestUser;
use OneLogin\Saml2\Auth;
use OneLogin\Saml2\Error;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Exception as MockException;
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
    #[DataProvider('provideCases')]
    public function test(?TokenInterface $token, ?string $sessionIndex): void
    {
        try {
            $auth = $this->createMock(Auth::class);

            if ($token instanceof SamlToken) {
                $auth->expects(self::once())
                    ->method('processSLO')
                    ->willThrowException(new Error('error'))
                ;

                $auth->method('getSLOurl')->willReturn('some_slo_url');

                $auth->expects(self::once())
                    ->method('logout')
                    ->with(null, [], 'tester', $sessionIndex)
                ;
            } else {
                $auth->expects(self::never())->method('logout');
            }

            $authRegistry = new AuthRegistry();
            $authRegistry->addService('default', 'default', $auth);

            $request = Request::create('/logout', 'GET', ['idp' => 'default', 'sp' => 'default']);

            $event = $this->createMock(LogoutEvent::class);
            $event->method('getRequest')->willReturn($request);
            $event->method('getToken')->willReturn($token);

            $listener = new SamlLogoutListener($authRegistry, new IdpResolver('idp', 'sp'));
            $listener->processSingleLogout($event);
        } catch (MockException $e) {
            self::fail('Mock creation failed: '.$e->getMessage());
        }
    }

    /**
     * @return iterable<array{token: ?TokenInterface, sessionIndex: ?string}>
     *
     * @throws MockException
     */
    public static function provideCases(): iterable
    {
        yield 'No Auth service' => [
            'token' => null,
            'sessionIndex' => null,
        ];

        yield 'Custom Auth service without SAML token' => [
            'token' => self::createStub(TokenInterface::class),
            'sessionIndex' => null,
        ];

        yield 'Logout without session index' => [
            'token' => new SamlToken(new TestUser('tester'), 'foo', [], []),
            'sessionIndex' => null,
        ];

        yield 'Logout with session index' => [
            'token' => new SamlToken(
                new TestUser('tester'),
                'foo',
                [],
                [SamlAuthenticator::SESSION_INDEX_ATTRIBUTE => 'session_index']
            ),
            'sessionIndex' => 'session_index',
        ];
    }
}
