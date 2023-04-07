<?php declare(strict_types=1);
// SPDX-License-Identifier: BSD-3-Clause

namespace Nbgrp\OneloginSamlBundle\DependencyInjection\Compiler;

use Nbgrp\OneloginSamlBundle\Onelogin\AuthFactory;
use Nbgrp\OneloginSamlBundle\Onelogin\AuthRegistryInterface;
use OneLogin\Saml2\Auth;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Initialize AuthRegistry with Auth services according OneLogin settings.
 */
class AuthRegistryCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $authRegistry = $container->findDefinition(AuthRegistryInterface::class);

        $oneloginSettings = $container->getParameter('nbgrp_onelogin_saml.onelogin_settings');
        if (!\is_array($oneloginSettings)) {
            throw new \UnexpectedValueException('OneLogin settings should be an array.');
        }

        /** @var array $settings */
        foreach ($oneloginSettings as $key => $settings) {
            $authDefinition = new Definition(Auth::class, [$settings]);
            $authDefinition->setFactory(new Reference(AuthFactory::class));
            $authRegistry->addMethodCall('addService', [$key, $authDefinition]);
        }
    }
}
