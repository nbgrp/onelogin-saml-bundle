<?php declare(strict_types=1);
// SPDX-License-Identifier: BSD-3-Clause

namespace Nbgrp\OneloginSamlBundle\DependencyInjection\Compiler;

use Nbgrp\OneloginSamlBundle\Onelogin\Auth;
use Nbgrp\OneloginSamlBundle\Onelogin\AuthRegistryInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Initialize AuthRegistry with Auth services according OneLogin settings.
 */
class AuthRegistryCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $authRegistry = $container->getDefinition(AuthRegistryInterface::class);

        $oneloginSettings = $container->getParameter('nbgrp_onelogin_saml.onelogin_settings');
        if (!\is_array($oneloginSettings)) {
            throw new \UnexpectedValueException('OneLogin settings should be an array.');
        }

        /** @var array $settings */
        foreach ($oneloginSettings as $key => $settings) {
            $authRegistry->addMethodCall('addService', [$key, new Definition(Auth::class, [new Reference(RequestStack::class), $settings])]);
        }
    }
}
