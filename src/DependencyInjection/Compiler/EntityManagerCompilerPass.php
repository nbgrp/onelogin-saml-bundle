<?php
// SPDX-License-Identifier: BSD-3-Clause

declare(strict_types=1);

namespace Nbgrp\OneloginSamlBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Replaces default entity manager in SAML user listeners with custom one.
 */
class EntityManagerCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter('nbgrp_onelogin_saml.entity_manager')) {
            return;
        }

        $entityManagerName = $container->getParameter('nbgrp_onelogin_saml.entity_manager');
        if (!\is_string($entityManagerName)) {
            throw new \UnexpectedValueException('Entity manager name should be a string value.');
        }

        $emDefinition = 'doctrine.orm.'.$entityManagerName.'_entity_manager';
        if (!$container->hasDefinition($emDefinition)) {
            return;
        }

        foreach (array_keys($container->findTaggedServiceIds('nbgrp.saml_user_listener')) as $id) {
            $listenerDefinition = $container->getDefinition($id);
            $listenerDefinition->replaceArgument(0, new Reference($emDefinition));
        }
    }
}
