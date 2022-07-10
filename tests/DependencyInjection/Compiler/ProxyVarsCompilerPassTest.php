<?php declare(strict_types=1);
// SPDX-License-Identifier: BSD-3-Clause

namespace Nbgrp\Tests\OneloginSamlBundle\DependencyInjection\Compiler;

use Nbgrp\OneloginSamlBundle\DependencyInjection\Compiler\ProxyVarsCompilerPass;
use OneLogin\Saml2\Utils;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @covers \Nbgrp\OneloginSamlBundle\DependencyInjection\Compiler\ProxyVarsCompilerPass
 *
 * @internal
 */
final class ProxyVarsCompilerPassTest extends TestCase
{
    /**
     * @dataProvider processProvider
     */
    public function testProcess(bool $useVars): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('nbgrp_onelogin_saml.use_proxy_vars', $useVars);

        /** @psalm-suppress DeprecatedClass */
        (new ProxyVarsCompilerPass())->process($container);
        self::assertSame($useVars, Utils::getProxyVars());
    }

    public function processProvider(): \Generator
    {
        yield 'Use vars' => [
            'useVars' => true,
        ];

        yield 'Do not use vars' => [
            'useVars' => false,
        ];
    }
}
