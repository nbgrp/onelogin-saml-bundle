<?php
// SPDX-License-Identifier: BSD-3-Clause

declare(strict_types=1);

namespace Nbgrp\OneloginSamlBundle\Security\Http\Authentication;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Authentication\DefaultAuthenticationSuccessHandler;
use Symfony\Component\Security\Http\HttpUtils;

/**
 * Returns value of RelayState request parameter (GET or POST) as target url
 * (if it does not equal to the login path).
 */
class SamlAuthenticationSuccessHandler extends DefaultAuthenticationSuccessHandler
{
    public const RELAY_STATE = 'RelayState';

    /** @psalm-suppress MixedArrayAccess */
    protected function determineTargetUrl(Request $request): string
    {
        if ($this->options['always_use_default_target_path']) {
            return (string) $this->options['default_target_path'];
        }

        /** @psalm-suppress InvalidArgument */
        $relayState = $request->query->get(self::RELAY_STATE, $request->request->get(self::RELAY_STATE));
        if ($relayState !== null && $this->httpUtils instanceof HttpUtils) {
            /** @psalm-suppress RedundantCastGivenDocblockType */
            $relayState = (string) $relayState;
            if ($relayState !== $this->httpUtils->generateUri($request, (string) $this->options['login_path'])) {
                return $relayState;
            }
        }

        return parent::determineTargetUrl($request);
    }
}
