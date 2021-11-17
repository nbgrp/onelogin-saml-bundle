<?php declare(strict_types=1);
// SPDX-License-Identifier: BSD-3-Clause

use Nbgrp\OneloginSamlBundle\Controller\AssertionConsumerService;
use Nbgrp\OneloginSamlBundle\Controller\Login;
use Nbgrp\OneloginSamlBundle\Controller\Logout;
use Nbgrp\OneloginSamlBundle\Controller\Metadata;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * @suppress PhanUndeclaredFunctionInCallable
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add('saml_metadata', '/saml/metadata')
        ->controller(Metadata::class)
        ->defaults(['idp' => null])
    ;

    $routes->add('saml_acs', '/saml/acs')
        ->controller(AssertionConsumerService::class)
        ->defaults(['idp' => null])
        ->methods(['POST'])
    ;

    $routes->add('saml_login', '/saml/login')
        ->controller(Login::class)
        ->defaults(['idp' => null])
    ;

    $routes->add('saml_logout', '/saml/logout')
        ->controller(Logout::class)
        ->defaults(['idp' => null])
    ;
};
