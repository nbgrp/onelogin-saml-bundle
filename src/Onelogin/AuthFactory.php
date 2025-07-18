<?php
// SPDX-License-Identifier: BSD-3-Clause

declare(strict_types=1);

namespace Nbgrp\OneloginSamlBundle\Onelogin;

use OneLogin\Saml2\Auth;
use OneLogin\Saml2\Error;
use Symfony\Component\HttpFoundation\RequestStack;

final class AuthFactory
{
    public const SCHEME_AND_HOST_PLACEHOLDER = '<request_scheme_and_host>';

    public function __construct(
        private readonly RequestStack $requestStack,
    ) {}

    /**
     * Create a new OneLogin Auth instance with settings.
     *
     * @param array<string, mixed> $settings
     *
     * @throws Error
     */
    public function __invoke(array $settings): Auth
    {
        $request = $this->requestStack->getMainRequest();
        $settings = self::replaceSchemeAndHostPlaceholder(
            $settings,
            $request?->getSchemeAndHttpHost() ?? 'http://localhost',
        );

        return new Auth($settings);
    }

    /**
     * Replace the scheme and host placeholder in the settings array.
     *
     * @param array<string, mixed> $settings
     *
     * @return array<string, mixed>
     */
    private static function replaceSchemeAndHostPlaceholder(array $settings, string $replace): array
    {
        if (isset($settings['baseurl'])) {
            $settings['baseurl'] = str_replace(
                self::SCHEME_AND_HOST_PLACEHOLDER,
                $replace,
                (string) $settings['baseurl']
            );
        }

        if (isset($settings['sp']) && \is_array($settings['sp'])) {
            /** @var array<string, mixed> $sp */
            $sp = $settings['sp'];

            if (isset($sp['entityId'])) {
                $sp['entityId'] = str_replace(
                    self::SCHEME_AND_HOST_PLACEHOLDER,
                    $replace,
                    (string) $sp['entityId']
                );
            }

            if (isset($sp['assertionConsumerService']) && \is_array($sp['assertionConsumerService'])) {
                /** @var array<string, mixed> $acs */
                $acs = $sp['assertionConsumerService'];

                if (isset($acs['url'])) {
                    $acs['url'] = str_replace(
                        self::SCHEME_AND_HOST_PLACEHOLDER,
                        $replace,
                        (string) $acs['url']
                    );
                }

                $sp['assertionConsumerService'] = $acs;
            }

            if (isset($sp['singleLogoutService']) && \is_array($sp['singleLogoutService'])) {
                /** @var array<string, mixed> $sls */
                $sls = $sp['singleLogoutService'];

                if (isset($sls['url'])) {
                    $sls['url'] = str_replace(
                        self::SCHEME_AND_HOST_PLACEHOLDER,
                        $replace,
                        (string) $sls['url']
                    );
                }

                $sp['singleLogoutService'] = $sls;
            }

            $settings['sp'] = $sp;
        }

        return $settings;
    }
}
