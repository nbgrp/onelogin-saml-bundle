<?php
// SPDX-License-Identifier: BSD-3-Clause

declare(strict_types=1);

namespace Nbgrp\Tests\OneloginSamlBundle\DependencyInjection;

use Nbgrp\OneloginSamlBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

/**
 * @covers \Nbgrp\OneloginSamlBundle\DependencyInjection\Configuration
 *
 * @internal
 */
final class ConfigurationTest extends TestCase
{
    private Processor $processor;

    /**
     * @dataProvider provideValidConfigCases
     */
    public function testValidConfig(array $config, array $expected): void
    {
        self::assertSame($expected, $this->processor->processConfiguration(new Configuration(), [$config]));
    }

    public function provideValidConfigCases(): iterable
    {
        yield 'Simple configuration' => [
            'config' => [
                'onelogin_settings' => [
                    'test' => [
                        'idp' => [
                            'entityId' => 'test-idp',
                            'singleSignOnService' => [
                                'url' => 'http://example.com/sso',
                            ],
                        ],
                    ],
                ],
            ],
            'expected' => [
                'onelogin_settings' => [
                    'test' => [
                        'idp' => [
                            'entityId' => 'test-idp',
                            'singleSignOnService' => [
                                'url' => 'http://example.com/sso',
                            ],
                        ],
                        'baseurl' => '<request_scheme_and_host>/saml/',
                        'sp' => [
                            'entityId' => '<request_scheme_and_host>/saml/metadata',
                            'assertionConsumerService' => [
                                'url' => '<request_scheme_and_host>/saml/acs',
                            ],
                            'singleLogoutService' => [
                                'url' => '<request_scheme_and_host>/saml/logout',
                            ],
                        ],
                    ],
                ],
                'use_proxy_vars' => false,
                'idp_parameter_name' => 'idp',
            ],
        ];

        yield 'Extended configuration' => [
            'config' => [
                'onelogin_settings' => [
                    'first' => [
                        'baseurl' => 'http://example.com',
                        'strict' => false,
                        'debug' => true,
                        'idp' => [
                            'entityId' => 'first-idp',
                            'singleSignOnService' => [
                                'url' => 'http://example.com/sso',
                                'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
                            ],
                            'singleLogoutService' => [
                                'url' => 'http://example.com/slo',
                                'responseUrl' => 'http://example.com/response',
                                'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
                            ],
                            'x509cert' => 'cert-data',
                            'certFingerprint' => 'cert-finterprint',
                            'certFingerprintAlgorithm' => 'sha256',
                            'x509certMulti' => [
                                'signing' => ['<cert1-sign-string>', '<cert2-sign-string>'],
                                'encryption' => ['<cert1-enc-string>', '<cert2-enc-string>'],
                            ],
                        ],
                        'sp' => [
                            'entityId' => 'test-sp',
                            'assertionConsumerService' => [
                                'url' => 'http://example.com/saml/acs',
                                'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST',
                            ],
                            'singleLogoutService' => [
                                'url' => 'http://example.com/saml/logout',
                                'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
                            ],
                            'NameIDFormat' => 'urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress',
                            'x509cert' => 'x509cert-data',
                            'privateKey' => 'private-key',
                            'x509certNew' => 'some-new-x509cert',
                        ],
                        'compress' => [
                            'requests' => false,
                            'responses' => false,
                        ],
                        'security' => [
                            'nameIdEncrypted' => false,
                            'authnRequestsSigned' => true,
                            'logoutRequestSigned' => true,
                            'logoutResponseSigned' => true,
                            'signMetadata' => false,
                            'wantMessagesSigned' => false,
                            'wantAssertionsEncrypted' => false,
                            'wantAssertionsSigned' => true,
                            'wantNameId' => false,
                            'wantNameIdEncrypted' => false,
                            'requestedAuthnContext' => false,
                            'requestedAuthnContextComparison' => 'exact',
                            'wantXMLValidation' => false,
                            'relaxDestinationValidation' => false,
                            'destinationStrictlyMatches' => false,
                            'allowRepeatAttributeName' => false,
                            'rejectUnsolicitedResponsesWithInResponseTo' => false,
                            'signatureAlgorithm' => 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256',
                            'digestAlgorithm' => 'http://www.w3.org/2001/04/xmlenc#sha256',
                            'encryption_algorithm' => 'http://www.w3.org/2001/04/xmlenc#aes256-cbc',
                            'lowercaseUrlencoding' => false,
                        ],
                        'contactPerson' => [
                            'support' => [
                                'givenName' => 'support person',
                                'emailAddress' => 'support@example.com',
                            ],
                            'billing' => [
                                'givenName' => 'billing person',
                                'emailAddress' => 'billing@example.com',
                            ],
                            'other' => [
                                'givenName' => 'other person',
                                'emailAddress' => 'other@example.com',
                            ],
                        ],
                        'organization' => [
                            'en-US' => [
                                'name' => 'Example',
                                'displayname' => 'Example org.',
                                'url' => 'http://example.org',
                            ],
                        ],
                    ],
                    'second' => [
                        'idp' => [
                            'entityId' => 'second-idp',
                            'singleSignOnService' => [
                                'url' => 'http://idp.net',
                                'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
                            ],
                        ],
                        'sp' => [
                            'entityId' => 'test-sp',
                            'assertionConsumerService' => [
                                'url' => 'http://example.com/saml/acs',
                                'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST',
                            ],
                            'singleLogoutService' => [],
                        ],
                    ],
                ],
                'use_proxy_vars' => true,
                'idp_parameter_name' => 'custom-idp',
                'entity_manager_name' => 'custom-em',
            ],
            'expected' => [
                'onelogin_settings' => [
                    'first' => [
                        'baseurl' => 'http://example.com',
                        'strict' => false,
                        'debug' => true,
                        'idp' => [
                            'entityId' => 'first-idp',
                            'singleSignOnService' => [
                                'url' => 'http://example.com/sso',
                                'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
                            ],
                            'singleLogoutService' => [
                                'url' => 'http://example.com/slo',
                                'responseUrl' => 'http://example.com/response',
                                'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
                            ],
                            'x509cert' => 'cert-data',
                            'certFingerprint' => 'cert-finterprint',
                            'certFingerprintAlgorithm' => 'sha256',
                            'x509certMulti' => [
                                'signing' => [
                                    '<cert1-sign-string>',
                                    '<cert2-sign-string>',
                                ],
                                'encryption' => [
                                    '<cert1-enc-string>',
                                    '<cert2-enc-string>',
                                ],
                            ],
                        ],
                        'sp' => [
                            'entityId' => 'test-sp',
                            'assertionConsumerService' => [
                                'url' => 'http://example.com/saml/acs',
                                'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST',
                            ],
                            'singleLogoutService' => [
                                'url' => 'http://example.com/saml/logout',
                                'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
                            ],
                            'NameIDFormat' => 'urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress',
                            'x509cert' => 'x509cert-data',
                            'privateKey' => 'private-key',
                            'x509certNew' => 'some-new-x509cert',
                        ],
                        'compress' => [
                            'requests' => false,
                            'responses' => false,
                        ],
                        'security' => [
                            'nameIdEncrypted' => false,
                            'authnRequestsSigned' => true,
                            'logoutRequestSigned' => true,
                            'logoutResponseSigned' => true,
                            'signMetadata' => false,
                            'wantMessagesSigned' => false,
                            'wantAssertionsEncrypted' => false,
                            'wantAssertionsSigned' => true,
                            'wantNameId' => false,
                            'wantNameIdEncrypted' => false,
                            'requestedAuthnContext' => false,
                            'requestedAuthnContextComparison' => 'exact',
                            'wantXMLValidation' => false,
                            'relaxDestinationValidation' => false,
                            'destinationStrictlyMatches' => false,
                            'allowRepeatAttributeName' => false,
                            'rejectUnsolicitedResponsesWithInResponseTo' => false,
                            'signatureAlgorithm' => 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256',
                            'digestAlgorithm' => 'http://www.w3.org/2001/04/xmlenc#sha256',
                            'encryption_algorithm' => 'http://www.w3.org/2001/04/xmlenc#aes256-cbc',
                            'lowercaseUrlencoding' => false,
                        ],
                        'contactPerson' => [
                            'support' => [
                                'givenName' => 'support person',
                                'emailAddress' => 'support@example.com',
                            ],
                            'billing' => [
                                'givenName' => 'billing person',
                                'emailAddress' => 'billing@example.com',
                            ],
                            'other' => [
                                'givenName' => 'other person',
                                'emailAddress' => 'other@example.com',
                            ],
                        ],
                        'organization' => [
                            'en_US' => [
                                'name' => 'Example',
                                'displayname' => 'Example org.',
                                'url' => 'http://example.org',
                            ],
                        ],
                    ],
                    'second' => [
                        'idp' => [
                            'entityId' => 'second-idp',
                            'singleSignOnService' => [
                                'url' => 'http://idp.net',
                                'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
                            ],
                        ],
                        'sp' => [
                            'entityId' => 'test-sp',
                            'assertionConsumerService' => [
                                'url' => 'http://example.com/saml/acs',
                                'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST',
                            ],
                            'singleLogoutService' => [
                                'url' => '<request_scheme_and_host>/saml/logout',
                            ],
                        ],
                        'baseurl' => '<request_scheme_and_host>/saml/',
                    ],
                ],
                'use_proxy_vars' => true,
                'idp_parameter_name' => 'custom-idp',
                'entity_manager_name' => 'custom-em',
            ],
        ];
    }

    /**
     * @dataProvider provideConfigWithInvalidOneLoginSettingsExceptionCases
     */
    public function testConfigWithInvalidOneLoginSettingsException(array $config, string $expectedMessage): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($expectedMessage);
        $this->processor->processConfiguration(new Configuration(), [$config]);
    }

    public function provideConfigWithInvalidOneLoginSettingsExceptionCases(): iterable
    {
        yield 'Empty idp OneLogin settings' => [
            'config' => [
                'onelogin_settings' => [
                    'test' => [
                        'baseurl' => 'http://example.com',
                    ],
                ],
            ],
            'expectedMessage' => 'The child config "idp" under "nbgrp_onelogin_saml.onelogin_settings.test" must be configured.',
        ];

        yield 'Empty SSO for IdP OneLogin settings' => [
            'config' => [
                'onelogin_settings' => [
                    'test' => [
                        'idp' => [
                            'entityId' => 'idp-id',
                        ],
                    ],
                ],
            ],
            'expectedMessage' => 'The child config "singleSignOnService" under "nbgrp_onelogin_saml.onelogin_settings.test.idp" must be configured.',
        ];

        yield 'Invalid SSO binding for IdP OneLogin settings' => [
            'config' => [
                'onelogin_settings' => [
                    'test' => [
                        'idp' => [
                            'entityId' => 'idp-id',
                            'singleSignOnService' => [
                                'url' => 'http://example.org/sso',
                                'binding' => 'foo',
                            ],
                        ],
                    ],
                ],
            ],
            'expectedMessage' => 'Invalid configuration for path "nbgrp_onelogin_saml.onelogin_settings.test.idp.singleSignOnService.binding": invalid value.',
        ];

        yield 'Invalid SLO binding for IdP OneLogin settings' => [
            'config' => [
                'onelogin_settings' => [
                    'test' => [
                        'idp' => [
                            'entityId' => 'idp-id',
                            'singleSignOnService' => [
                                'url' => 'http://example.org/sso',
                            ],
                            'singleLogoutService' => [
                                'url' => 'http://example.org/slo',
                                'binding' => 'foo',
                            ],
                        ],
                    ],
                ],
            ],
            'expectedMessage' => 'Invalid configuration for path "nbgrp_onelogin_saml.onelogin_settings.test.idp.singleLogoutService.binding": invalid value.',
        ];

        yield 'Invalid certFingerprintAlgorithm for IdP OneLogin settings' => [
            'config' => [
                'onelogin_settings' => [
                    'test' => [
                        'idp' => [
                            'entityId' => 'idp-id',
                            'singleSignOnService' => [
                                'url' => 'http://example.org/sso',
                            ],
                            'certFingerprintAlgorithm' => 'invalid',
                        ],
                    ],
                ],
            ],
            'expectedMessage' => 'The value "invalid" is not allowed for path "nbgrp_onelogin_saml.onelogin_settings.test.idp.certFingerprintAlgorithm". Permissible values: "sha1", "sha256", "sha384", "sha512"',
        ];

        yield 'Invalid assertionConsumerService binding for SP OneLogin settings' => [
            'config' => [
                'onelogin_settings' => [
                    'test' => [
                        'idp' => [
                            'entityId' => 'idp-id',
                            'singleSignOnService' => [
                                'url' => 'http://example.org/sso',
                                'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
                            ],
                        ],
                        'sp' => [
                            'entityId' => 'sp-id',
                            'assertionConsumerService' => [
                                'url' => 'http://example.com',
                                'binding' => 'invalid',
                            ],
                        ],
                    ],
                ],
            ],
            'expectedMessage' => 'Invalid configuration for path "nbgrp_onelogin_saml.onelogin_settings.test.sp.assertionConsumerService.binding": invalid value.',
        ];

        yield 'Invalid singleLogoutService binding for SP OneLogin settings' => [
            'config' => [
                'onelogin_settings' => [
                    'test' => [
                        'idp' => [
                            'entityId' => 'idp-id',
                            'singleSignOnService' => [
                                'url' => 'http://example.org/sso',
                                'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
                            ],
                        ],
                        'sp' => [
                            'entityId' => 'sp-id',
                            'assertionConsumerService' => [
                                'url' => 'http://example.com',
                            ],
                            'singleLogoutService' => [
                                'url' => 'http://example.com/logout',
                                'binding' => 'invalid',
                            ],
                        ],
                    ],
                ],
            ],
            'expectedMessage' => 'Invalid configuration for path "nbgrp_onelogin_saml.onelogin_settings.test.sp.singleLogoutService.binding": invalid value.',
        ];

        yield 'Invalid NameIDFormat for SP OneLogin settings' => [
            'config' => [
                'onelogin_settings' => [
                    'test' => [
                        'idp' => [
                            'entityId' => 'idp-id',
                            'singleSignOnService' => [
                                'url' => 'http://example.org/sso',
                                'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
                            ],
                        ],
                        'sp' => [
                            'entityId' => 'sp-id',
                            'assertionConsumerService' => [
                                'url' => 'http://example.com',
                            ],
                            'NameIDFormat' => 'invalid',
                        ],
                    ],
                ],
            ],
            'expectedMessage' => 'Invalid configuration for path "nbgrp_onelogin_saml.onelogin_settings.test.sp.NameIDFormat": invalid value.',
        ];

        yield 'Invalid requestedAuthnContext type for security OneLogin settings' => [
            'config' => [
                'onelogin_settings' => [
                    'test' => [
                        'idp' => [
                            'entityId' => 'idp-id',
                            'singleSignOnService' => [
                                'url' => 'http://example.org/sso',
                                'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
                            ],
                        ],
                        'sp' => [
                            'entityId' => 'sp-id',
                            'assertionConsumerService' => [
                                'url' => 'http://example.com',
                                'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
                            ],
                        ],
                        'security' => [
                            'requestedAuthnContext' => 0,
                        ],
                    ],
                ],
            ],
            'expectedMessage' => 'Invalid configuration for path "nbgrp_onelogin_saml.onelogin_settings.test.security.requestedAuthnContext": must be an array or a boolean.',
        ];

        yield 'Invalid requestedAuthnContext for security OneLogin settings' => [
            'config' => [
                'onelogin_settings' => [
                    'test' => [
                        'idp' => [
                            'entityId' => 'idp-id',
                            'singleSignOnService' => [
                                'url' => 'http://example.org/sso',
                                'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
                            ],
                        ],
                        'sp' => [
                            'entityId' => 'sp-id',
                            'assertionConsumerService' => [
                                'url' => 'http://example.com',
                                'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
                            ],
                        ],
                        'security' => [
                            'requestedAuthnContext' => ['unknown'],
                        ],
                    ],
                ],
            ],
            'expectedMessage' => 'Invalid configuration for path "nbgrp_onelogin_saml.onelogin_settings.test.security.requestedAuthnContext": invalid value.',
        ];

        yield 'No givenName for contact person OneLogin settings' => [
            'config' => [
                'onelogin_settings' => [
                    'test' => [
                        'idp' => [
                            'entityId' => 'idp-id',
                            'singleSignOnService' => [
                                'url' => 'http://example.org/sso',
                                'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
                            ],
                        ],
                        'sp' => [
                            'entityId' => 'sp-id',
                            'assertionConsumerService' => [
                                'url' => 'http://example.com',
                                'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
                            ],
                        ],
                        'contactPerson' => [
                            'technical' => [
                                'emailAddress' => 'email@example.com',
                            ],
                        ],
                    ],
                ],
            ],
            'expectedMessage' => 'The child config "givenName" under "nbgrp_onelogin_saml.onelogin_settings.test.contactPerson.technical" must be configured.',
        ];

        yield 'No emailAddress for contact person OneLogin settings' => [
            'config' => [
                'onelogin_settings' => [
                    'test' => [
                        'idp' => [
                            'entityId' => 'idp-id',
                            'singleSignOnService' => [
                                'url' => 'http://example.org/sso',
                                'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
                            ],
                        ],
                        'sp' => [
                            'entityId' => 'sp-id',
                            'assertionConsumerService' => [
                                'url' => 'http://example.com',
                                'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
                            ],
                        ],
                        'contactPerson' => [
                            'technical' => [
                                'givenName' => 'name',
                            ],
                        ],
                    ],
                ],
            ],
            'expectedMessage' => 'The child config "emailAddress" under "nbgrp_onelogin_saml.onelogin_settings.test.contactPerson.technical" must be configured.',
        ];

        yield 'No name for organization OneLogin settings' => [
            'config' => [
                'onelogin_settings' => [
                    'test' => [
                        'idp' => [
                            'entityId' => 'idp-id',
                            'singleSignOnService' => [
                                'url' => 'http://example.org/sso',
                                'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
                            ],
                        ],
                        'sp' => [
                            'entityId' => 'sp-id',
                            'assertionConsumerService' => [
                                'url' => 'http://example.com',
                                'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
                            ],
                        ],
                        'organization' => [
                            'en-US' => [
                                'displayname' => 'Org',
                            ],
                        ],
                    ],
                ],
            ],
            'expectedMessage' => 'The child config "name" under "nbgrp_onelogin_saml.onelogin_settings.test.organization.en_US" must be configured.',
        ];

        yield 'No displayname for organization OneLogin settings' => [
            'config' => [
                'onelogin_settings' => [
                    'test' => [
                        'idp' => [
                            'entityId' => 'idp-id',
                            'singleSignOnService' => [
                                'url' => 'http://example.org/sso',
                                'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
                            ],
                        ],
                        'sp' => [
                            'entityId' => 'sp-id',
                            'assertionConsumerService' => [
                                'url' => 'http://example.com',
                                'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
                            ],
                        ],
                        'organization' => [
                            'en-US' => [
                                'name' => 'org',
                            ],
                        ],
                    ],
                ],
            ],
            'expectedMessage' => 'The child config "displayname" under "nbgrp_onelogin_saml.onelogin_settings.test.organization.en_US" must be configured.',
        ];

        yield 'No url for organization OneLogin settings' => [
            'config' => [
                'onelogin_settings' => [
                    'test' => [
                        'idp' => [
                            'entityId' => 'idp-id',
                            'singleSignOnService' => [
                                'url' => 'http://example.org/sso',
                                'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
                            ],
                        ],
                        'sp' => [
                            'entityId' => 'sp-id',
                            'assertionConsumerService' => [
                                'url' => 'http://example.com',
                                'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
                            ],
                        ],
                        'organization' => [
                            'en-US' => [
                                'name' => 'org',
                                'displayname' => 'Org',
                            ],
                        ],
                    ],
                ],
            ],
            'expectedMessage' => 'The child config "url" under "nbgrp_onelogin_saml.onelogin_settings.test.organization.en_US" must be configured.',
        ];

        yield 'Empty idp_parameter_name' => [
            'config' => [
                'onelogin_settings' => [
                    'test' => [
                        'idp' => [
                            'entityId' => 'idp-id',
                            'singleSignOnService' => [
                                'url' => 'http://example.org/sso',
                                'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
                            ],
                        ],
                        'sp' => [
                            'entityId' => 'sp-id',
                            'assertionConsumerService' => [
                                'url' => 'http://example.com',
                                'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
                            ],
                        ],
                    ],
                ],
                'idp_parameter_name' => '',
            ],
            'expectedMessage' => 'The path "nbgrp_onelogin_saml.idp_parameter_name" cannot contain an empty value, but got "".',
        ];

        yield 'Empty entity_manager_name' => [
            'config' => [
                'onelogin_settings' => [
                    'test' => [
                        'idp' => [
                            'entityId' => 'idp-id',
                            'singleSignOnService' => [
                                'url' => 'http://example.org/sso',
                                'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
                            ],
                        ],
                        'sp' => [
                            'entityId' => 'sp-id',
                            'assertionConsumerService' => [
                                'url' => 'http://example.com',
                                'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
                            ],
                        ],
                    ],
                ],
                'entity_manager_name' => '',
            ],
            'expectedMessage' => 'The path "nbgrp_onelogin_saml.entity_manager_name" cannot contain an empty value, but got "".',
        ];
    }

    protected function setUp(): void
    {
        $this->processor = new Processor();
    }
}
