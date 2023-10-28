<?php
// SPDX-License-Identifier: BSD-3-Clause

declare(strict_types=1);

namespace Nbgrp\Tests\OneloginSamlBundle\Idp;

use Nbgrp\OneloginSamlBundle\Idp\IdpResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * @covers \Nbgrp\OneloginSamlBundle\Idp\IdpResolver
 *
 * @internal
 */
final class IdpResolverTest extends TestCase
{
    private IdpResolver $resolver;

    /**
     * @dataProvider provideResolveCases
     */
    public function testResolve(Request $request, ?string $expected): void
    {
        self::assertSame($expected, $this->resolver->resolve($request));
    }

    public function provideResolveCases(): iterable
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
