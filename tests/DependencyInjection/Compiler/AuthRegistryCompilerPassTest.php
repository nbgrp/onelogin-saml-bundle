<?php declare(strict_types=1);
// SPDX-License-Identifier: BSD-3-Clause

namespace Nbgrp\Tests\OneloginSamlBundle\DependencyInjection\Compiler;

use Nbgrp\OneloginSamlBundle\DependencyInjection\Compiler\AuthRegistryCompilerPass;
use Nbgrp\OneloginSamlBundle\Onelogin\AuthRegistryInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @covers \Nbgrp\OneloginSamlBundle\DependencyInjection\Compiler\AuthRegistryCompilerPass
 *
 * @internal
 */
final class AuthRegistryCompilerPassTest extends TestCase
{
    public function testSuccessProcess(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(AuthRegistryInterface::class, new Definition(AuthRegistryInterface::class));
        $container->setParameter('nbgrp_onelogin_saml.onelogin_settings', [
            'first' => [],
            'second' => [],
        ]);

        (new AuthRegistryCompilerPass())->process($container);

        $authRegistryDefinition = $container->getDefinition(AuthRegistryInterface::class);
        self::assertCount(2, $authRegistryDefinition->getMethodCalls());

        /** @var array $call */
        foreach ($authRegistryDefinition->getMethodCalls() as $call) {
            self::assertSame('addService', reset($call));
        }
    }

    public function testInvalidOneLoginSettingsInProcessException(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(AuthRegistryInterface::class, new Definition(AuthRegistryInterface::class));
        $container->setParameter('nbgrp_onelogin_saml.onelogin_settings', 'invalid');
        $compilerPass = new AuthRegistryCompilerPass();

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('OneLogin settings should be an array.');
        $compilerPass->process($container);
    }
}
