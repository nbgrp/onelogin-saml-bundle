<?php
// SPDX-License-Identifier: BSD-3-Clause

declare(strict_types=1);

namespace Nbgrp\Tests\OneloginSamlBundle\Security\Http\Authenticator\Token;

use Nbgrp\OneloginSamlBundle\Security\Http\Authenticator\Token\SamlToken;
use Nbgrp\Tests\OneloginSamlBundle\TestUser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(SamlToken::class)]
final class SamlTokenTest extends TestCase
{
    /**
     * @param array<string, mixed> $attributes
     */
    #[DataProvider('provideTokenCases')]
    public function testToken(array $attributes): void
    {
        $user = new TestUser('tester');
        $token = new SamlToken($user, 'firewallName', ['ROLE_USER', 'ROLE_EXTRA'], $attributes);

        self::assertSame($token->getUserIdentifier(), 'tester');
        self::assertSame($token->getRoleNames(), ['ROLE_USER', 'ROLE_EXTRA']);
        self::assertSame($token->getAttributes(), $attributes);
    }

    /** @return iterable<array<string,mixed>> */
    public static function provideTokenCases(): iterable
    {
        yield 'Empty attributes' => [
            'attributes' => [],
        ];

        yield 'Not empty attributes' => [
            'attributes' => [
                'username' => 'tester',
                'email' => 'tester@example.com',
            ],
        ];
    }
}
