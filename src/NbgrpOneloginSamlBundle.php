<?php
// SPDX-License-Identifier: BSD-3-Clause

declare(strict_types=1);

namespace Nbgrp\OneloginSamlBundle;

use Nbgrp\OneloginSamlBundle\DependencyInjection\Compiler\AuthRegistryCompilerPass;
use Nbgrp\OneloginSamlBundle\DependencyInjection\Compiler\EntityManagerCompilerPass;
use Nbgrp\OneloginSamlBundle\DependencyInjection\Security\Factory\SamlFactory;
use Nbgrp\OneloginSamlBundle\DependencyInjection\Security\UserProvider\SamlUserProviderFactory;
use Symfony\Bundle\SecurityBundle\DependencyInjection\SecurityExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * @final
 */
class NbgrpOneloginSamlBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $extension = $container->getExtension('security');
        if ($extension instanceof SecurityExtension) {
            $extension->addAuthenticatorFactory(new SamlFactory());
            $extension->addUserProviderFactory(new SamlUserProviderFactory());
        }

        $container
            ->addCompilerPass(new EntityManagerCompilerPass())
            ->addCompilerPass(new AuthRegistryCompilerPass())
        ;
    }
}
