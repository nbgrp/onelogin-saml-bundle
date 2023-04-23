<?php
// SPDX-License-Identifier: BSD-3-Clause

declare(strict_types=1);

namespace Nbgrp\OneloginSamlBundle\DependencyInjection\Compiler;

use OneLogin\Saml2\Utils;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/** @psalm-suppress DeprecatedClass */
trigger_deprecation('nbgrp/onelogin-saml-bundle', '1.2', 'The "%s" class is deprecated.', ProxyVarsCompilerPass::class);

/**
 * Allows using `X-Forwarded-*` headers by OneLogin PHP SAML toolkit.
 *
 * @deprecated since nbgrp/onelogin-saml-bundle 1.2
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
