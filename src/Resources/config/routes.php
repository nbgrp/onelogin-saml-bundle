<?php
// SPDX-License-Identifier: BSD-3-Clause

declare(strict_types=1);

use Nbgrp\OneloginSamlBundle\Controller;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * @suppress PhanUndeclaredFunctionInCallable
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add('saml_metadata', '/saml/metadata')
        ->controller(Controller\Metadata::class)
        ->defaults(['idp' => null])
    ;

    $routes->add('saml_acs', '/saml/acs')
        ->controller(Controller\AssertionConsumerService::class)
        ->defaults(['idp' => null])
        ->methods(['POST'])
    ;

    $routes->add('saml_login', '/saml/login')
        ->controller(Controller\Login::class)
        ->defaults(['idp' => null])
    ;

    $routes->add('saml_logout', '/saml/logout')
        ->controller(Controller\Logout::class)
        ->defaults(['idp' => null])
    ;
};
