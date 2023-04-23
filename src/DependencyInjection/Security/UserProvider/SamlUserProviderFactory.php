<?php
// SPDX-License-Identifier: BSD-3-Clause

declare(strict_types=1);

namespace Nbgrp\OneloginSamlBundle\DependencyInjection\Security\UserProvider;

use Nbgrp\OneloginSamlBundle\Security\User\SamlUserProvider;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\UserProvider\UserProviderFactoryInterface;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Security\Core\User\UserInterface;

class SamlUserProviderFactory implements UserProviderFactoryInterface
{
    public function create(ContainerBuilder $container, string $id, array $config): void
    {
        $container
            ->setDefinition($id, new ChildDefinition(SamlUserProvider::class))
            ->addArgument($config['user_class'])
            ->addArgument($config['default_roles'])
        ;
    }

    public function getKey(): string
    {
        return 'saml';
    }

    /**
     * @suppress PhanUndeclaredMethod
     */
    public function addConfiguration(NodeDefinition $builder): void
    {
        // @formatter:off
        /** @phpstan-ignore-next-line */
        $builder
            ->children()
                ->scalarNode('user_class')
                    ->isRequired()
                    ->cannotBeEmpty()
                    ->validate()
                        ->ifTrue(static fn ($value) => !is_a($value, UserInterface::class, true))
                        ->thenInvalid('You should provide user class implementing '.UserInterface::class.' interface.')
                    ->end()
                ->end()
                ->arrayNode('default_roles')
                    ->prototype('scalar')->end()
                    ->defaultValue(['ROLE_USER'])
                ->end()
            ->end()
        ;
        // @formatter:on
    }
}
