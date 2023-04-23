<?php
// SPDX-License-Identifier: BSD-3-Clause

declare(strict_types=1);

namespace Nbgrp\Tests\OneloginSamlBundle\DependencyInjection\Security\Factory;

use Nbgrp\OneloginSamlBundle\DependencyInjection\Security\Factory\SamlFactory;
use Nbgrp\OneloginSamlBundle\EventListener\User\UserCreatedListener;
use Nbgrp\OneloginSamlBundle\EventListener\User\UserModifiedListener;
use Nbgrp\OneloginSamlBundle\Security\Http\Authentication\SamlAuthenticationSuccessHandler;
use Nbgrp\OneloginSamlBundle\Security\Http\Authenticator\SamlAuthenticator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @covers \Nbgrp\OneloginSamlBundle\DependencyInjection\Security\Factory\SamlFactory
 *
 * @internal
 */
final class SamlFactoryTest extends TestCase
{
    private SamlFactory $factory;

    public function testDefaultConfiguration(): void
    {
        $nodeDefinition = new ArrayNodeDefinition($this->factory->getKey());
        $this->factory->addConfiguration($nodeDefinition);

        $node = $nodeDefinition->getNode();
        self::assertSame([
            'remember_me' => true,
            'success_handler' => SamlAuthenticationSuccessHandler::class,
            'check_path' => '/login_check',
            'use_forward' => false,
            'require_previous_session' => false,
            'login_path' => '/login',
            'identifier_attribute' => null,
            'use_attribute_friendly_name' => false,
            'user_factory' => null,
            'token_factory' => null,
            'persist_user' => false,
            'always_use_default_target_path' => false,
            'default_target_path' => '/',
            'target_path_parameter' => '_target_path',
            'use_referer' => false,
            'failure_path' => null,
            'failure_forward' => false,
            'failure_path_parameter' => '_failure_path',
        ], $node->finalize($node->normalize([])));
    }

    public function testCreateAuthenticator(): void
    {
        $container = new ContainerBuilder();

        $baseAuthenticatorDefinition = new Definition(SamlAuthenticator::class);
        $baseAuthenticatorDefinition->setArguments(array_fill(0, 10, null));
        $container->setDefinition(SamlAuthenticator::class, $baseAuthenticatorDefinition);

        $baseUserCreatedListenerDefinition = new Definition(UserCreatedListener::class);
        $baseUserCreatedListenerDefinition->setArguments(array_fill(0, 2, null));
        $container->setDefinition(SamlAuthenticator::class, $baseUserCreatedListenerDefinition);

        $baseUserModifiedListenerDefinition = new Definition(UserModifiedListener::class);
        $baseUserModifiedListenerDefinition->setArguments(array_fill(0, 2, null));
        $container->setDefinition(SamlAuthenticator::class, $baseUserModifiedListenerDefinition);

        $this->factory = new SamlFactory();
        $config = [
            'persist_user' => true,
            'user_factory' => 'saml_user_factory',
            'success_handler' => SamlAuthenticationSuccessHandler::class,
            'unexpected_option' => true,
        ];

        self::assertSame('security.authenticator.saml.foo', $this->factory->createAuthenticator($container, 'foo', $config, 'user_provider'));

        $authenticatorDefinition = $container->getDefinition('security.authenticator.saml.foo');

        /** @var Reference $userProviderReference */
        $userProviderReference = $authenticatorDefinition->getArgument(1);
        self::assertSame('user_provider', (string) $userProviderReference);

        /** @var Reference $successHandlerReference */
        $successHandlerReference = $authenticatorDefinition->getArgument(4);
        $successHandlerDefinition = $container->getDefinition((string) $successHandlerReference);
        /** @var Reference|ChildDefinition|mixed $samlSuccessHandlerReference */
        $samlSuccessHandlerReference = $successHandlerDefinition->getArgument(0);
        if ($samlSuccessHandlerReference instanceof Reference) {
            self::assertSame(SamlAuthenticationSuccessHandler::class, (string) $samlSuccessHandlerReference);
        } elseif ($samlSuccessHandlerReference instanceof ChildDefinition) {
            self::assertSame(SamlAuthenticationSuccessHandler::class, $samlSuccessHandlerReference->getParent());
        }

        /** @var array $options */
        $options = $authenticatorDefinition->getArgument(6);
        self::assertSame([
            'persist_user' => true,
            'user_factory' => 'saml_user_factory',
            'success_handler' => SamlAuthenticationSuccessHandler::class,
        ], $options);

        /** @var Reference $userFactoryReference */
        $userFactoryReference = $authenticatorDefinition->getArgument(7);
        self::assertSame('saml_user_factory', (string) $userFactoryReference);

        /** @var ChildDefinition $userCreatedListenerDefinition */
        $userCreatedListenerDefinition = $container->getDefinition('nbgrp_onelogin_saml.user_created_listener.foo');
        self::assertSame(UserCreatedListener::class, $userCreatedListenerDefinition->getParent());
        self::assertTrue($userCreatedListenerDefinition->getArgument(1));
        self::assertTrue($userCreatedListenerDefinition->hasTag('nbgrp.saml_user_listener'));

        /** @var ChildDefinition $userModifiedListenerDefinition */
        $userModifiedListenerDefinition = $container->getDefinition('nbgrp_onelogin_saml.user_modified_listener.foo');
        self::assertSame(UserModifiedListener::class, $userModifiedListenerDefinition->getParent());
        self::assertTrue($userModifiedListenerDefinition->getArgument(1));
        self::assertTrue($userModifiedListenerDefinition->hasTag('nbgrp.saml_user_listener'));
    }

    protected function setUp(): void
    {
        $this->factory = new SamlFactory();
    }
}
