<?php
// SPDX-License-Identifier: BSD-3-Clause

declare(strict_types=1);

namespace Nbgrp\Tests\OneloginSamlBundle\Security\Http\Authenticator\Passport\Badge;

use Nbgrp\OneloginSamlBundle\Security\Http\Authenticator\Passport\Badge\SamlAttributesBadge;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(SamlAttributesBadge::class)]
final class SamlAttributesBadgeTest extends TestCase
{
    /**
     * @param array<string,mixed> $attributes
     */
    #[DataProvider('provideBadgeCases')]
    public function testBadge(array $attributes): void
    {
        $badge = new SamlAttributesBadge($attributes);

        self::assertSame($attributes, $badge->getAttributes());
        self::assertTrue($badge->isResolved());
    }

    /** @return iterable<array<string,mixed>> */
    public static function provideBadgeCases(): iterable
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
