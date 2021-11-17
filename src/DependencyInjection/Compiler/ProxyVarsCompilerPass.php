<?php declare(strict_types=1);
// SPDX-License-Identifier: BSD-3-Clause

namespace Nbgrp\OneloginSamlBundle\DependencyInjection\Compiler;

use OneLogin\Saml2\Utils;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Allows using `X-Forwarded-*` headers by OneLogin PHP SAML toolkit.
 */
class ProxyVarsCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $useProxyVars = $container->getParameter('nbgrp_onelogin_saml.use_proxy_vars');
        if (\is_bool($useProxyVars)) {
            Utils::setProxyVars($useProxyVars);
        }
    }
}
