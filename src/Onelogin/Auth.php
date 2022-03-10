<?php declare(strict_types=1);
// SPDX-License-Identifier: BSD-3-Clause

namespace Nbgrp\OneloginSamlBundle\Onelogin;

use OneLogin\Saml2\Auth as BaseAuth;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class Auth extends BaseAuth
{
    private const PLACEHOLDER = '<request_host>';

    public function __construct(RequestStack $requestStack, ?array $settings = null)
    {
        $request = $requestStack->getMainRequest();

        if ($settings && $request) {
            $requestHost = $this->getRequestHost($request);
            $settings = $this->resolvePlaceholders($settings, $requestHost);
        }

        parent::__construct($settings);
    }

    private function getRequestHost(Request $request): string
    {
        $port = (int) $request->getPort();
        $host = sprintf('%s://%s', $request->getScheme(), $request->getHost());

        if (\in_array($port, [0, 80, 443], true)) {
            return $host;
        }

        return sprintf('%s:%d', $host, $port);
    }

    private function resolvePlaceholders(array $settings, string $requestHost): array
    {
        foreach (['assertionConsumerService', 'singleLogoutService'] as $service) {
            if (!isset($settings['sp'][$service]['url'])) {
                continue;
            }

            /** @psalm-suppress MixedArrayAssignment */
            $settings['sp'][$service]['url'] = $this->resolvePlaceholder((string) $settings['sp'][$service]['url'], $requestHost);
        }

        if (isset($settings['sp']['entityId'])) {
            $settings['sp']['entityId'] = $this->resolvePlaceholder((string) $settings['sp']['entityId'], $requestHost);
        }
        if (isset($settings['baseurl'])) {
            $settings['baseurl'] = $this->resolvePlaceholder((string) $settings['baseurl'], $requestHost);
        }

        return $settings;
    }

    private function resolvePlaceholder(string $value, string $requestHost): string
    {
        return str_replace(self::PLACEHOLDER, $requestHost, $value);
    }
}
