<?php
// SPDX-License-Identifier: BSD-3-Clause

declare(strict_types=1);

namespace Nbgrp\Tests\OneloginSamlBundle\Controller;

use Nbgrp\OneloginSamlBundle\Controller\Metadata;
use OneLogin\Saml2\Auth;
use OneLogin\Saml2\Settings;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Nbgrp\OneloginSamlBundle\Controller\Metadata
 *
 * @internal
 */
final class MetadataTest extends TestCase
{
    public function testInvoke(): void
    {
        $settings = $this->createMock(Settings::class);
        $settings
            ->method('getSPMetadata')
            ->willReturn('<?xml version="1.0" encoding="utf-8"?><some-xml-data />')
        ;

        $auth = $this->createMock(Auth::class);
        $auth
            ->method('getSettings')
            ->willReturn($settings)
        ;

        $response = (new Metadata())($auth);
        self::assertSame('<?xml version="1.0" encoding="utf-8"?><some-xml-data />', $response->getContent());
        self::assertSame('xml', $response->headers->get('Content-Type'));
    }
}
