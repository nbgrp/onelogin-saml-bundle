<?php
// SPDX-License-Identifier: BSD-3-Clause

declare(strict_types=1);

namespace Nbgrp\Tests\OneloginSamlBundle\DependencyInjection\Compiler;

use Nbgrp\OneloginSamlBundle\DependencyInjection\Compiler\EntityManagerCompilerPass;
use Nbgrp\OneloginSamlBundle\EventListener\User\UserCreatedListener;
use Nbgrp\OneloginSamlBundle\EventListener\User\UserModifiedListener;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @internal
 */
#[CoversClass(EntityManagerCompilerPass::class)]
final class EntityManagerCompilerPassTest extends TestCase
{
    public function testNoProcess(): void
    {
        $container = $this->createMock(ContainerBuilder::class);
        $container
            ->method('hasParameter')
            ->with('nbgrp_onelogin_saml.entity_manager')
            ->willReturn(false)
        ;
        $container
            ->expects(self::never())
            ->method('getParameter')
        ;

        (new EntityManagerCompilerPass())->process($container);
    }

    public function testNoEntityManagerProcess(): void
    {
        $container = $this->createMock(ContainerBuilder::class);
        $container
            ->method('hasParameter')
            ->with('nbgrp_onelogin_saml.entity_manager')
            ->willReturn(true)
        ;
        $container
            ->method('getParameter')
            ->with('nbgrp_onelogin_saml.entity_manager')
            ->willReturn('foo')
        ;
        $container
            ->method('hasDefinition')
            ->with('doctrine.orm.foo_entity_manager')
            ->willReturn(false)
        ;
        $container
            ->expects(self::never())
            ->method('findTaggedServiceIds')
        ;

        (new EntityManagerCompilerPass())->process($container);
    }

    public function testSuccessProcess(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('nbgrp_onelogin_saml.entity_manager', 'foo');
        $container->setDefinition('doctrine.orm.foo_entity_manager', new Definition());

        $userCreatedListener = new Definition(UserCreatedListener::class);
        $userCreatedListener->setAbstract(true);
        $userCreatedListener->setArguments([null, false]);
        $container->setDefinition(UserCreatedListener::class, $userCreatedListener);

        $listener1 = new ChildDefinition(UserCreatedListener::class);
        $listener1->addTag('nbgrp.saml_user_listener');
        $container->setDefinition('nbgrp.saml_user_listener_1', $listener1);

        $userModifiedListener = new Definition(UserModifiedListener::class);
        $userModifiedListener->setAbstract(true);
        $userModifiedListener->setArguments([null, false]);
        $container->setDefinition(UserModifiedListener::class, $userModifiedListener);

        $listener2 = new ChildDefinition(UserModifiedListener::class);
        $listener2->addTag('nbgrp.saml_user_listener');
        $container->setDefinition('nbgrp.saml_user_listener_2', $listener2);

        (new EntityManagerCompilerPass())->process($container);

        /** @var \Symfony\Component\DependencyInjection\Reference $reference */
        $reference = $listener1->getArgument(0);
        self::assertSame('doctrine.orm.foo_entity_manager', (string) $reference);
        /** @var \Symfony\Component\DependencyInjection\Reference $reference */
        $reference = $listener2->getArgument(0);
        self::assertSame('doctrine.orm.foo_entity_manager', (string) $reference);
    }

    public function testInvalidEntityMangerProcessException(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('nbgrp_onelogin_saml.entity_manager', false);
        $compilerPass = new EntityManagerCompilerPass();

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Entity manager name should be a string value.');
        $compilerPass->process($container);
    }
}
