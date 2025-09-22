<?php
// SPDX-License-Identifier: BSD-3-Clause

declare(strict_types=1);

namespace Nbgrp\Tests\OneloginSamlBundle\Idp;

use Nbgrp\OneloginSamlBundle\Idp\IdpResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(IdpResolver::class)]
final class IdpResolverTest extends TestCase
{
    private IdpResolver $resolver;

    #[DataProvider('provideResolveCases')]
    public function testResolve(Request $request, ?string $expected): void
    {
        self::assertSame($expected, $this->resolver->resolve($request));
    }

    public static function provideResolveCases(): iterable
    {
        yield 'Request with ipd in query' => [
            'request' => new Request(['idp' => 'query-idp']),
            'expected' => 'query-idp',
        ];

        yield 'Request with ipd in attributes' => [
            'request' => new Request([], [], ['idp' => 'attributes-idp']),
            'expected' => 'attributes-idp',
        ];

        yield 'Request without ipd' => [
            'request' => new Request(),
            'expected' => null,
        ];
    }

    protected function setUp(): void
    {
        $this->resolver = new IdpResolver('idp');
    }
}
