<?php
// SPDX-License-Identifier: BSD-3-Clause

declare(strict_types=1);

namespace Nbgrp\OneloginSamlBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

/**
 * @internal
 * @final
 */
class NbgrpOneloginSamlExtension extends Extension
{
    /** @psalm-suppress MixedArgument */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new PhpFileLoader($container, new FileLocator(\dirname(__DIR__).'/Resources/config'));
        $loader->load('services.php');

        $config = $this->processConfiguration(new Configuration(), $configs);

        $container->setParameter('nbgrp_onelogin_saml.onelogin_settings', $config['onelogin_settings']);
        $container->setParameter('nbgrp_onelogin_saml.use_proxy_vars', $config['use_proxy_vars']);
        $container->setParameter('nbgrp_onelogin_saml.idp_parameter_name', $config['idp_parameter_name']);

        if (\array_key_exists('entity_manager_name', $config)) {
            $container->setParameter('nbgrp_onelogin_saml.entity_manager', $config['entity_manager_name']);
        }
    }
}
