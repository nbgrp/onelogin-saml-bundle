<?php declare(strict_types=1);
// SPDX-License-Identifier: BSD-3-Clause

namespace Nbgrp\Tests\OneloginSamlBundle\DependencyInjection;

use Nbgrp\OneloginSamlBundle\DependencyInjection\NbgrpOneloginSamlExtension;
use Nbgrp\OneloginSamlBundle\EventListener\Security\SamlLogoutListener;
use Nbgrp\OneloginSamlBundle\EventListener\User\UserCreatedListener;
use Nbgrp\OneloginSamlBundle\EventListener\User\UserModifiedListener;
use Nbgrp\OneloginSamlBundle\Idp\IdpResolverInterface;
use Nbgrp\OneloginSamlBundle\Onelogin\AuthRegistryInterface;
use Nbgrp\OneloginSamlBundle\Security\Http\Authenticator\SamlAuthenticator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @covers \Nbgrp\OneloginSamlBundle\DependencyInjection\NbgrpOneloginSamlExtension
 *
 * @internal
 */
final class NbgrpOneloginSamlExtensionTest extends TestCase
{
    public function testLoad(): void
    {
        $container = new ContainerBuilder();
        $extension = new NbgrpOneloginSamlExtension();

        $container->registerExtension($extension);
        $config = [
            'onelogin_settings' => [
                'test' => [
                    'idp' => [
                        'entityId' => 'test-idp',
                        'singleSignOnService' => [
                            'url' => 'http://example.com/sso',
                        ],
                    ],
                    'sp' => [
                        'entityId' => 'test-sp',
                        'assertionConsumerService' => [
                            'url' => 'http://example.com/saml/acs',
                        ],
                    ],
                ],
            ],
            'idp_parameter_name' => 'custom-idp',
            'entity_manager_name' => 'custom-em',
        ];

        $extension->load(['nbgrp_onelogin_saml' => $config], $container);

        self::assertSame([
            'test' => [
                'idp' => [
                    'entityId' => 'test-idp',
                    'singleSignOnService' => [
                        'url' => 'http://example.com/sso',
                    ],
                ],
                'sp' => [
                    'entityId' => 'test-sp',
                    'assertionConsumerService' => [
                        'url' => 'http://example.com/saml/acs',
                    ],
                    'singleLogoutService' => [
                        'url' => '<request_scheme_and_host>/saml/logout',
                    ],
                ],
                'baseurl' => '<request_scheme_and_host>/saml/',
            ],
        ], $container->getParameter('nbgrp_onelogin_saml.onelogin_settings'));
        self::assertSame('custom-idp', $container->getParameter('nbgrp_onelogin_saml.idp_parameter_name'));
        self::assertSame('custom-em', $container->getParameter('nbgrp_onelogin_saml.entity_manager'));

        self::assertTrue($container->hasDefinition(IdpResolverInterface::class));
        self::assertTrue($container->hasDefinition(AuthRegistryInterface::class));
        self::assertTrue($container->hasDefinition(SamlAuthenticator::class));
        self::assertTrue($container->hasDefinition(SamlLogoutListener::class));
        self::assertTrue($container->hasDefinition(UserCreatedListener::class));
        self::assertTrue($container->hasDefinition(UserModifiedListener::class));
    }
}
