<?php declare(strict_types=1);
// SPDX-License-Identifier: BSD-3-Clause

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Doctrine\ORM\EntityManagerInterface;
use Nbgrp\OneloginSamlBundle\Controller;
use Nbgrp\OneloginSamlBundle\EventListener;
use Nbgrp\OneloginSamlBundle\Idp;
use Nbgrp\OneloginSamlBundle\Onelogin;
use Nbgrp\OneloginSamlBundle\Security;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Http\Event\LogoutEvent;
use Symfony\Component\Security\Http\HttpUtils;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->defaults()
        ->autowire()
        ->autoconfigure()
    ;

    $services->load('Nbgrp\\OneloginSamlBundle\\', \dirname(__DIR__, 2).'/*')
        ->exclude(\dirname(__DIR__, 2).'/{DependencyInjection,Event,Resources}')
    ;

    $services->set(Controller\Login::class)
        ->arg('$firewallMap', service('security.firewall.map'))
    ;

    $services->set(Idp\IdpResolverInterface::class, Idp\IdpResolver::class)
        ->arg('$idpParameterName', param('nbgrp_onelogin_saml.idp_parameter_name'))
    ;

    $services->set(Onelogin\AuthRegistryInterface::class, Onelogin\AuthRegistry::class);

    $services->set(Security\Http\Authenticator\SamlAuthenticator::class)
        ->tag('monolog.logger', ['channel' => 'security'])
        ->args([
            /* 0 */ service(HttpUtils::class),
            /* 1 */ abstract_arg('user provider'),
            /* 2 */ service(Idp\IdpResolverInterface::class),
            /* 3 */ service(Onelogin\AuthRegistryInterface::class),
            /* 4 */ abstract_arg('success handler'),
            /* 5 */ abstract_arg('failure handler'),
            /* 6 */ abstract_arg('options'),
            /* 7 */ null,  // user factory
            /* 8 */ service(EventDispatcherInterface::class)->nullOnInvalid(),
            /* 9 */ service(LoggerInterface::class)->nullOnInvalid(),
            /* 10 */ param('nbgrp_onelogin_saml.idp_parameter_name'),
        ])
    ;

    $services->set(EventListener\Security\SamlLogoutListener::class)
        ->tag('kernel.event_listener', ['event' => LogoutEvent::class])
    ;

    $services->set(EventListener\User\UserCreatedListener::class)
        ->abstract()
        ->args([
            service(EntityManagerInterface::class)->nullOnInvalid(),
            false,  // persist_user
        ])
    ;
    $services->set(EventListener\User\UserModifiedListener::class)
        ->abstract()
        ->args([
            service(EntityManagerInterface::class)->nullOnInvalid(),
            false,  // persist_user
        ])
    ;
};
