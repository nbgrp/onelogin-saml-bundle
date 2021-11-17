<?php declare(strict_types=1);
// SPDX-License-Identifier: BSD-3-Clause

namespace Nbgrp\Tests\OneloginSamlBundle\Security\Http\Authenticator\Token;

use Nbgrp\OneloginSamlBundle\Security\Http\Authenticator\Token\SamlToken;
use Nbgrp\Tests\OneloginSamlBundle\TestUser;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Nbgrp\OneloginSamlBundle\Security\Http\Authenticator\Token\SamlToken
 *
 * @internal
 */
final class SamlTokenTest extends TestCase
{
    /**
     * @dataProvider attributesProvider
     */
    public function testToken(array $attributes): void
    {
        $user = new TestUser('tester');
        $token = new SamlToken($user, 'fwname', ['ROLE_USER', 'ROLE_EXTRA'], $attributes);

        self::assertSame($token->getUserIdentifier(), 'tester');
        self::assertSame($token->getRoleNames(), ['ROLE_USER', 'ROLE_EXTRA']);
        self::assertSame($token->getAttributes(), $attributes);
    }

    public function attributesProvider(): \Generator
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
