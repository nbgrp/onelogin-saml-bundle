<?php
// SPDX-License-Identifier: BSD-3-Clause

declare(strict_types=1);

namespace Nbgrp\Tests\OneloginSamlBundle\Controller;

use Nbgrp\OneloginSamlBundle\Controller\Login;
use Nbgrp\OneloginSamlBundle\Security\Http\Authenticator\SamlAuthenticator;
use OneLogin\Saml2\Auth;
use OneLogin\Saml2\Settings;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Exception as MockObjectException;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security\FirewallConfig;
use Symfony\Bundle\SecurityBundle\Security\FirewallMap;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

/**
 * @internal
 */
#[CoversClass(Login::class)]
final class LoginTest extends TestCase
{
    /**
     * @throws MockObjectException
     */
    public function testInvokeWithRejectUnsolicitedResponsesWithInResponseTo(): void
    {
        $firewallMap = self::createStub(FirewallMap::class);
        $firewallMap
            ->method('getFirewallConfig')
            ->willReturn(new FirewallConfig('foo', 'bar'))
        ;

        $auth = $this->createMock(Auth::class);
        $auth
            ->method('login')
            ->with('/target-path-after-login')
            ->willReturn('/redirect_url')
        ;

        $settingsMock = $this->createMock(Settings::class);
        $settingsMock
            ->method('getSecurityData')
            ->willReturn(['rejectUnsolicitedResponsesWithInResponseTo' => true])
        ;
        $auth
            ->method('getSettings')
            ->willReturn($settingsMock)
        ;
        $auth
            ->method('getLastRequestID')
            ->willReturn('requestID')
        ;

        $request = Request::create('/login');
        $session = new Session(new MockArraySessionStorage());
        $session->set('_security.foo.target_path', '/target-path-after-login');
        $request->setSession($session);

        $response = (new Login($firewallMap))($request, $auth);

        self::assertSame('/redirect_url', $response->headers->get('Location'));
        self::assertSame('requestID', $session->get(SamlAuthenticator::LAST_REQUEST_ID));
    }

    /**
     * @throws MockObjectException
     */
    public function testInvokeWithoutRejectUnsolicitedResponsesWithInResponseTo(): void
    {
        $firewallMap = self::createStub(FirewallMap::class);
        $firewallMap
            ->method('getFirewallConfig')
            ->willReturn(new FirewallConfig('foo', 'bar'))
        ;

        $auth = $this->createMock(Auth::class);
        $auth
            ->method('login')
            ->with('/target-path-after-login')
            ->willReturn('/redirect_url')
        ;

        $settingsMock = $this->createMock(Settings::class);
        $settingsMock
            ->method('getSecurityData')
            ->willReturn([])
        ;
        $auth
            ->method('getSettings')
            ->willReturn($settingsMock)
        ;
        $auth
            ->method('getLastRequestID')
            ->willReturn('requestID')
        ;

        $request = Request::create('/login');
        $session = new Session(new MockArraySessionStorage());
        $session->set('_security.foo.target_path', '/target-path-after-login');
        $request->setSession($session);

        $response = (new Login($firewallMap))($request, $auth);

        self::assertSame('/redirect_url', $response->headers->get('Location'));
        self::assertFalse($session->has(SamlAuthenticator::LAST_REQUEST_ID));
    }

    /**
     * @throws MockObjectException
     */
    #[DataProvider('provideErrorExceptionCases')]
    public function testErrorException(Request $request, string $expectedMessage): void
    {
        $firewallMap = $this->createMock(FirewallMap::class);
        $firewallMap
            ->method('getFirewallConfig')
            ->willReturn(new FirewallConfig('foo', 'bar'))
        ;

        $auth = self::createStub(Auth::class);

        $controller = new Login($firewallMap);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage($expectedMessage);
        $controller($request, $auth);
    }

    /**
     * Provides test cases for error exceptions.
     *
     * @return iterable<array{request: Request, expectedMessage: string}>
     *
     * @throws MockObjectException
     */
    public static function provideErrorExceptionCases(): iterable
    {
        yield 'From attributes' => [
            'request' => (static function () {
                $request = Request::create('/login');
                $request->attributes->set(SecurityRequestAttributes::AUTHENTICATION_ERROR, new \Exception('Error from attributes'));

                return $request;
            })(),
            'expectedMessage' => 'Error from attributes',
        ];

        yield 'From session' => [
            'request' => (static function () {
                $request = Request::create('/login');
                $session = new Session(new MockArraySessionStorage());
                $session->set(SecurityRequestAttributes::AUTHENTICATION_ERROR, new \Exception('Error from session'));
                $request->setSession($session);

                return $request;
            })(),
            'expectedMessage' => 'Error from session',
        ];
    }

    public function testAuthLoginWithoutRedirectUrlException(): void
    {
        try {
            $firewallMap = $this->createMock(FirewallMap::class);
            $firewallMap
                ->method('getFirewallConfig')
                ->willReturn(new FirewallConfig('foo', 'bar'))
            ;

            $auth = $this->createMock(Auth::class);
            $auth
                ->method('login')
                ->willReturn(null)
            ;

            $request = Request::create('/login');
            $request->setSession(new Session(new MockArraySessionStorage()));

            $controller = new Login($firewallMap);

            $this->expectException(\RuntimeException::class);
            $controller($request, $auth);
        } catch (MockObjectException $e) {
            self::fail('Failed to create mocks for OneLogin Auth. '.$e->getMessage());
        }
    }

    /**
     * @throws MockObjectException
     */
    public function testUnknownFirewallException(): void
    {
        $firewallMap = $this->createMock(FirewallMap::class);
        $firewallMap
            ->method('getFirewallConfig')
            ->willReturn(null)
        ;

        $auth = self::createStub(Auth::class);

        $controller = new Login($firewallMap);
        $request = Request::create('/login');
        $request->setSession(new Session(new MockArraySessionStorage()));

        $this->expectException(ServiceUnavailableHttpException::class);
        $this->expectExceptionMessage('Unknown firewall.');
        $controller($request, $auth);
    }
}
