<?php declare(strict_types=1);
// SPDX-License-Identifier: BSD-3-Clause

namespace Nbgrp\Tests\OneloginSamlBundle\Security\Http\Authenticator\Passport\Badge;

use Nbgrp\OneloginSamlBundle\Security\Http\Authenticator\Passport\Badge\SamlAttributesBadge;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Nbgrp\OneloginSamlBundle\Security\Http\Authenticator\Passport\Badge\SamlAttributesBadge
 *
 * @internal
 */
final class SamlAttributesBadgeTest extends TestCase
{
    /**
     * @dataProvider attributesProvider
     */
    public function testBadge(array $attributes): void
    {
        $badge = new SamlAttributesBadge($attributes);

        self::assertSame($attributes, $badge->getAttributes());
        self::assertTrue($badge->isResolved());
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
