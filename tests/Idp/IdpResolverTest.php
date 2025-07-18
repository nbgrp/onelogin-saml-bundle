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
    /** @psalm-suppress PropertyNotSetInConstructor */
    private IdpResolver $resolver;

    /** @param array{idp:string, sp:string} $expected */
    #[DataProvider('provideResolveCases')]
    public function testResolve(Request $request, array $expected): void
    {
        self::assertSame($expected, $this->resolver->resolve($request));
    }

    /**
     * @return iterable<array{request:Request,expected:array{idp:string,sp:string}}>
     */
    public static function provideResolveCases(): iterable
    {
        yield 'Request with ipd in query' => [
            'request' => new Request(['idp' => 'query-idp']),
            'expected' => ['idp' => 'query-idp', 'sp' => 'default'],
        ];

        yield 'Request with ipd and sp in query' => [
            'request' => new Request(['idp' => 'query-idp', 'sp' => 'query-sp']),
            'expected' => ['idp' => 'query-idp', 'sp' => 'query-sp'],
        ];

        yield 'Request with ipd in attributes' => [
            'request' => new Request([], [], ['idp' => 'attributes-idp']),
            'expected' => ['idp' => 'attributes-idp', 'sp' => 'default'],
        ];

        yield 'Request with ipd and sp in attributes' => [
            'request' => new Request([], [], ['idp' => 'attributes-idp', 'sp' => 'attributes-sp']),
            'expected' => ['idp' => 'attributes-idp', 'sp' => 'attributes-sp'],
        ];

        yield 'Request without ipd' => [
            'request' => new Request(),
            'expected' => ['idp' => 'default', 'sp' => 'default'],
        ];
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->resolver = new IdpResolver('idp', 'sp');
    }
}
