<?php
// SPDX-License-Identifier: BSD-3-Clause

declare(strict_types=1);

namespace Nbgrp\Tests\OneloginSamlBundle\Onelogin;

use Nbgrp\OneloginSamlBundle\Onelogin\AuthFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @covers \Nbgrp\OneloginSamlBundle\Onelogin\AuthFactory
 *
 * @internal
 */
final class AuthFactoryTest extends TestCase
{
    public function testReplace(): void
    {
        $request = Request::create('', server: ['HTTPS' => 'on']);
        $request->headers->set('Host', 'example.com');

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $factory = new AuthFactory($requestStack);

        $auth = $factory([
            'baseurl' => '<request_scheme_and_host>/saml/',
            'idp' => [
                'entityId' => 'test-idp',
                'singleSignOnService' => [
                    'url' => 'https://example.com/saml',
                ],
                'x509cert' => 'cert-data',
            ],
            'sp' => [
                'entityId' => '<request_scheme_and_host>/saml/metadata',
                'assertionConsumerService' => [
                    'url' => '<request_scheme_and_host>/saml/acs',
                ],
                'singleLogoutService' => [
                    'url' => '<request_scheme_and_host>/saml/logout',
                ],
            ],
        ]);

        $authSettings = $auth->getSettings();
        $spData = $authSettings->getSPData();

        self::assertSame('https://example.com/saml/', $authSettings->getBaseURL());
        self::assertSame('https://example.com/saml/metadata', $spData['entityId'] ?? '');
        self::assertSame('https://example.com/saml/acs', $spData['assertionConsumerService']['url'] ?? '');
        self::assertSame('https://example.com/saml/logout', $spData['singleLogoutService']['url'] ?? '');
    }
}
