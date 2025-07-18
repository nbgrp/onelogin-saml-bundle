<?php
// SPDX-License-Identifier: BSD-3-Clause

declare(strict_types=1);

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
    #[\Override]
    public function process(ContainerBuilder $container): void
    {
        $authRegistry = $container->getDefinition(AuthRegistryInterface::class);

        $oneloginSettings = $container->getParameter('nbgrp_onelogin_saml.onelogin_settings');
        if (!\is_array($oneloginSettings)) {
            throw new \UnexpectedValueException('OneLogin settings should be an array.');
        }

        /** @var array<string,mixed> $idpSettings */
        foreach ($oneloginSettings as $idpKey => $idpSettings) {
            /** @var array<string, mixed> $settings */
            $settings = $idpSettings;
            if (isset($idpSettings['sp'])) {
                if (!\is_array($idpSettings['sp'])) {
                    throw new \UnexpectedValueException('OneLogin SP settings should be an array.');
                }
                /** @var array<string,mixed>|null $spSettings */
                foreach ($idpSettings['sp'] as $spKey => $spSettings) {
                    if (!\is_array($spSettings)) {
                        throw new \UnexpectedValueException('OneLogin SP settings for key "'.(string) $spKey.'" should be an array.');
                    }

                    $settings['sp'] = $spSettings;
                    $authDefinition = new Definition(Auth::class, [$settings]);
                    $authDefinition->setFactory(new Reference(AuthFactory::class));
                    $authRegistry->addMethodCall('addService', [$idpKey, $spKey, $authDefinition]);
                }
            } else {
                $settings['sp'] = 'default';
                $authDefinition = new Definition(Auth::class, [$settings]);
                $authDefinition->setFactory(new Reference(AuthFactory::class));
                $authRegistry->addMethodCall('addService', [$idpKey, 'default', $authDefinition]);
            }
        }
    }
}
