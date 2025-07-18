<?php
// SPDX-License-Identifier: BSD-3-Clause

declare(strict_types=1);

namespace Nbgrp\Tests\OneloginSamlBundle\Security\Http\Authentication;

use Nbgrp\OneloginSamlBundle\Security\Http\Authentication\SamlAuthenticationSuccessHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Exception as MockObjectException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\HttpUtils;

/**
 * @internal
 */
#[CoversClass(SamlAuthenticationSuccessHandler::class)]
final class SamlAuthenticationSuccessHandlerTest extends TestCase
{
    /** @param array<string,mixed> $options */
    #[DataProvider('provideHandlerCases')]
    public function testHandler(array $options, Request $request, string $expectedLocation): void
    {
        try {
            $token = self::createStub(TokenInterface::class);
            $urlGenerator = $this->createConfiguredMock(UrlGeneratorInterface::class, [
                'generate' => 'http://localhost/login',
            ]);
            $handler = new SamlAuthenticationSuccessHandler(new HttpUtils($urlGenerator), $options);
            $response = $handler->onAuthenticationSuccess($request, $token);

            self::assertNotNull($response);
            self::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
            self::assertSame($expectedLocation, $response->headers->get('Location'));
        } catch (MockObjectException $e) {
            self::fail('Failed to create mocks for OneLogin Auth. '.$e->getMessage());
        }
    }

    /** @return iterable<array<string,mixed>> */
    public static function provideHandlerCases(): iterable
    {
        yield 'Always use default target path' => [
            'options' => [
                'always_use_default_target_path' => true,
                'default_target_path' => '/default',
            ],
            'request' => Request::create('/'),
            'expectedLocation' => 'http://localhost/default',
        ];

        yield 'Relay state from query string' => [
            'options' => [],
            'request' => Request::create('/', 'GET', [
                'RelayState' => 'http://localhost/from-query-relay-state',
            ]),
            'expectedLocation' => 'http://localhost/from-query-relay-state',
        ];

        yield 'Relay state from request' => [
            'options' => [],
            'request' => Request::create('/', 'POST', [
                'RelayState' => 'http://localhost/from-request-relay-state',
            ]),
            'expectedLocation' => 'http://localhost/from-request-relay-state',
        ];

        yield 'Relay state as login page' => [
            'options' => [
                'login_path' => '/login',
            ],
            'request' => Request::create('/', 'GET', [
                '_target_path' => '/custom',
                'RelayState' => 'http://localhost/login',
            ]),
            'expectedLocation' => 'http://localhost/custom',
        ];

        yield 'Default target path' => [
            'options' => [
                'default_target_path' => '/parent-default',
            ],
            'request' => Request::create('/'),
            'expectedLocation' => 'http://localhost/parent-default',
        ];
    }

    public function testEmptyRelayState(): void
    {
        try {
            $request = Request::create('/', 'GET', ['RelayState' => '']);
            $token = self::createStub(TokenInterface::class);
            $handler = new SamlAuthenticationSuccessHandler(new HttpUtils(self::createStub(UrlGeneratorInterface::class)));

            $handler->onAuthenticationSuccess($request, $token);

            self::assertSame('', (string) $request->get('RelayState'), 'RelayState should be empty after handling');
            self::assertSame('', (string) $request->get('_target_path'), 'Target path should be empty after handling');
            self::assertSame('', (string) $request->get('SAMLResponse'), 'SAMLResponse should be empty after handling');
            self::assertSame('', (string) $request->get('SAMLRequest'), 'SAMLRequest should be empty after handling');
        } catch (MockObjectException $e) {
            self::fail('Failed to create mocks for OneLogin Auth. '.$e->getMessage());
        }
    }
}
