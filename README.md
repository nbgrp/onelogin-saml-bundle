# OneloginSamlBundle

[![Latest Stable Version](http://poser.pugx.org/nbgrp/onelogin-saml-bundle/v)](https://packagist.org/packages/nbgrp/onelogin-saml-bundle)
[![Latest Unstable Version](http://poser.pugx.org/nbgrp/onelogin-saml-bundle/v/unstable)](https://packagist.org/packages/nbgrp/onelogin-saml-bundle)
[![Total Downloads](http://poser.pugx.org/nbgrp/onelogin-saml-bundle/downloads)](https://packagist.org/packages/nbgrp/onelogin-saml-bundle)
[![License](http://poser.pugx.org/nbgrp/onelogin-saml-bundle/license)](https://packagist.org/packages/nbgrp/onelogin-saml-bundle)
[![Gitter](https://badges.gitter.im/nbgrp/community.svg)](https://gitter.im/nbgrp/community)

[![PHP Version Require](http://poser.pugx.org/nbgrp/onelogin-saml-bundle/require/php)](https://packagist.org/packages/nbgrp/onelogin-saml-bundle)
[![Codecov](https://codecov.io/gh/nbgrp/onelogin-saml-bundle/branch/1.x/graph/badge.svg?token=H17751BTW4)](https://codecov.io/gh/nbgrp/onelogin-saml-bundle)
[![Audit](https://github.com/nbgrp/onelogin-saml-bundle/actions/workflows/audit.yml/badge.svg)](https://github.com/nbgrp/onelogin-saml-bundle/actions/workflows/audit.yml)

[![SymfonyInsight](https://insight.symfony.com/projects/ed7b9263-179c-442a-9f45-6877e4e6dbdb/small.svg)](https://insight.symfony.com/projects/ed7b9263-179c-442a-9f45-6877e4e6dbdb)

## Overview

[OneLogin SAML](https://github.com/onelogin/php-saml) Symfony Bundle.

> This bundle depends on Symfony 6 and newer. <br>
> For older Symfony versions you can
> use [hslavich/oneloginsaml-bundle](https://github.com/hslavich/OneloginSamlBundle)
> which this bundle based on.

## Installation

```
composer require nbgrp/onelogin-saml-bundle
```

If you use Symfony Flex it enables the bundle automatically. Otherwise, to enable the bundle add the
following code in `config/bundles.php`:

``` php
return [
    // ...
    Nbgrp\OneloginSamlBundle\NbgrpOneloginSamlBundle::class => ['all' => true],
];
```

## Configuration

To configure the bundle you need to add configuration in `config/packages/nbgrp_onelogin_saml.yaml`.
You can use any configuration format (yaml, xml, or php), but for convenience in this document will
be used yaml.

> Check https://github.com/onelogin/php-saml#settings for more info about OneLogin PHP SAML
> settings.

> You can use `<request_scheme_and_host>` placeholder in the following configuration values which
> will be replaced by the appropriate values from the `Request` object:
>
> * onelogin_settings.sp.entityId
> * onelogin_settings.sp.assertionConsumerService.url
> * onelogin_settings.sp.singleLogoutService.url
> * onelogin_settings.baseurl
>
> Pay attention
> to [trusted proxies settings](https://symfony.com/doc/current/deployment/proxies.html)
> if you're running your application behind a load balancer or a reverse proxy.

``` yaml
nbgrp_onelogin_saml:
    onelogin_settings:
        default:
            # Mandatory SAML settings
            idp:
                entityId: 'https://id.example.com/saml2/idp/metadata.php'
                singleSignOnService:
                    url: 'https://id.example.com/saml2/idp/SSOService.php'
                    binding: 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect'
                singleLogoutService:
                    url: 'https://id.example.com/saml2/idp/SingleLogoutService.php'
                    binding: 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect'
                x509cert: 'MIIC...'
            sp:
                entityId: 'https://myapp.com/saml/metadata'  #  Default: '<request_scheme_and_host>/saml/metadata'
                assertionConsumerService:
                    url: 'https://myapp.com/saml/acs'  #  Default: '<request_scheme_and_host>/saml/acs'
                    binding: 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST'
                singleLogoutService:
                    url: 'https://myapp.com/saml/logout'  #  Default: '<request_scheme_and_host>/saml/logout'
                    binding: 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect'
                privateKey: 'MIIE...'
            # Optional SAML settings
            baseurl: 'https://myapp.com/saml/'  #  Default: '<request_scheme_and_host>/saml/'
            strict: true
            debug: true
            security:
                nameIdEncrypted: false
                authnRequestsSigned: false
                logoutRequestSigned: false
                logoutResponseSigned: false
                signMetadata: false
                wantMessagesSigned: false
                wantAssertionsEncrypted: false
                wantAssertionsSigned: true
                wantNameId: false
                wantNameIdEncrypted: false
                requestedAuthnContext: true
                requestedAuthnContextComparison: 'exact'
                wantXMLValidation: false
                relaxDestinationValidation: false
                destinationStrictlyMatches: true
                allowRepeatAttributeName: false
                rejectUnsolicitedResponsesWithInResponseTo: false
                signatureAlgorithm: 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256'
                digestAlgorithm: 'http://www.w3.org/2001/04/xmlenc#sha256'
                encryption_algorithm: 'http://www.w3.org/2001/04/xmlenc#aes256-cbc'
                lowercaseUrlencoding: false
            contactPerson:
                technical:
                    givenName: 'Tech User'
                    emailAddress: 'techuser@example.com'
                support:
                    givenName: 'Support User'
                    emailAddress: 'supportuser@example.com'
                administrative:
                    givenName: 'Administrative User'
                    emailAddress: 'administrativeuser@example.com'
            organization:
                en-US:
                    name: 'Example'
                    displayname: 'Example'
                    url: 'http://example.com'
            compress:
                requests: false
                responses: false
        # Optional another one SAML settings (see Multiple IdP below)
        another:
            idp:
                # ...
            sp:
                # ...
            # ...
    # Optional parameters
    use_proxy_vars: true
    idp_parameter_name: 'custom-idp'
    entity_manager_name: 'custom-em'
```

There are few extra parameters for `idp` and `sp` sections. You can read more about them from
OneLogin PHP SAML docs.

Instead of specify IdP and SP x509 certificates and private keys, you can store them in OneLogin PHP
SAML [certs directory](https://github.com/onelogin/php-saml#certs) or use global constant
`ONELOGIN_CUSTOMPATH` to specify custom directory (complete path will be
`ONELOGIN_CUSTOMPATH.'certs/'`).

If you do not want to set some contactPerson or organization info, do not add those parameters
instead of leaving them blank.

Configure user provider and firewall in `config/packages/security.yaml`:

``` yml
security:
    # ...

    providers:
        saml_provider:
            ##  Basic provider instantiates a user with identifier and default roles
            saml:
                user_class: 'App\Entity\User'
                default_roles: ['ROLE_USER']

    firewalls:
        main:
            pattern: ^/
            saml:
                ##  Match SAML attribute 'uid' with user identifier.
                ##  Otherwise, used \OneLogin\Saml2\Auth::getNameId() method by default.
                identifier_attribute: uid
                ##  Use the attribute's friendlyName instead of the name.
                use_attribute_friendly_name: true
                check_path: saml_acs
                login_path: saml_login
            logout:
                path: saml_logout

    access_control:
        - { path: ^/saml/(metadata|login|acs), roles: PUBLIC_ACCESS }
        - { path: ^/, roles: ROLE_USER }
```

Edit your `config/routes.yaml`:

``` yml
nbgrp_saml:
    resource: "@NbgrpOneloginSamlBundle/Resources/config/routes.php"
```

### Multiple IdP

You can configure more than one OneLogin PHP SAML settings for multiple IdP. To do this you need to
specify SAML settings for each IdP (sections with `default` and `another` keys in configuration
above) and pass the name of the necessary IdP by a query string parameter `idp` or a request
attribute with the same name. You can use another name with help of `idp_parameter_name` bundle
parameter.

> To use appropriate SAML settings, all requests to bundle routes should contain correct IdP
> parameter.

If a request has no query parameter or attribute with IdP value, the first key
in `onelogin_settings` section will be used as default IdP.

### Using reverse proxy

When you use your application behind a reverse proxy and use `X-Forwarded-*` headers, you need to
set parameter `nbgrp_onelogin_saml.use_proxy_vars = true` to allow underlying OneLogin library
determine request protocol, host and port correctly.

## Optional features

### Inject SAML attributes into User object

To be able to inject SAML attributes into user object, you must implement `SamlUserInterface`.

``` php
<?php

namespace App\Entity;

use Nbgrp\OneloginSamlBundle\Security\User\SamlUserInterface;

class User implements SamlUserInterface
{
    private $username;
    private $email;

    // ...

    public function setSamlAttributes(array $attributes)
    {
        $this->email = $attributes['mail'][0];
    }
}
```

> In addition to injecting SAML attributes to user, you can get them by `getAttributes` method
> from current security token (that should be an instance of
> `Nbgrp\OneloginSamlBundle\Security\Http\Authenticator\Token\SamlToken`).

### Integration with classic login form

You can integrate SAML authentication with traditional login form by editing your `security.yaml`:

``` yml
security:
    enable_authenticator_manager: true

    providers:
        user_provider:
            # ...

    firewalls:
        main:
            saml:
                # ...

            ##  Traditional login form
            form_login:
                login_path: /login
                check_path: /login_check
                # ...

            logout:
                path: saml_logout
```

Then you can add a link to route `saml_login` in your login page in order to start SAML sign-on.

``` html
<a href="{{ path('saml_login') }}">SAML Login</a>
```

If you use multiple IdP, you should specify it by `path` argument:

``` html
<a href="{{ path('saml_login', { idp: 'another' }) }}">SAML Login</a>
```

### Just-in-time user provisioning

> In order for a user to be provisioned, you must use a user provider that throws
> `UserNotFoundException` (e.g. `EntityUserProvider` as used in the example above).
> The `SamlUserProvider` does not throw this exception which will cause an empty user to be returned
> (if your user class not implements `Nbgrp\OneloginSamlBundle\Security\User\SamlUserInterface`).

It is possible to have a new user provisioned based on the received SAML attributes when the user
provider cannot find a user.

Create the user factory service by editing `services.yaml`:

``` yml
services:
    saml_user_factory:
        class: Nbgrp\OneloginSamlBundle\Security\User\SamlUserFactory
        arguments:
            ##  User class
            - App\Entity\User
            ##  Attribute mapping
            - password: 'notused'
              email: $mail
              name: $cn
              lastname: $sn
              roles: ['ROLE_USER']
              groups: $groups[]
```

> Mapping items with '$' at the beginning of values references to SAML attribute value. <br>
> Values with '[]' at the end will be presented as arrays (even if they originally are scalars).

Then add the created service id as the `user_factory` parameter into your firewall settings in
`security.yaml`:

``` yml
security:
    # ...

    providers:
        saml_provider:
            ##  Loads user from user repository
            entity:
                class: App\Entity\User
                property: username

    firewalls:
        main:
            provider: saml_provider
            saml:
                identifier_attribute: uid
                ##  User factory service
                user_factory: saml_user_factory
                # ...
```

Also, you can create your own User Factory that implements
`Nbgrp\OneloginSamlBundle\Security\User\SamlUserFactoryInterface`.

``` php
<?php

namespace App\Security;

use App\Entity\User;
use Nbgrp\OneloginSamlBundle\Security\User\SamlUserFactoryInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserFactory implements SamlUserFactoryInterface
{
    public function createUser(string $identifier, array $attributes): UserInterface
    {
        $user = new User();
        $user->setRoles(['ROLE_USER']);
        $user->setUsername($username);
        $user->setEmail($attributes['mail'][0]);
        // ...

        return $user;
    }
}
```

And add it into `services.yaml`:

``` yml
services:
    saml_user_factory:
        class: App\Security\UserFactory
```

### Persist user on creation and SAML attributes injection

> Symfony EventDispatcher component and Doctrine ORM are required.

If you want to persist user object after success authentication, you need to add `persist_user`
in you firewall settings in `security.yaml`:

``` yml
security:
    # ...

    firewalls:
        # ...

        main:
            saml:
                # ...
                persist_user: true
```

To use non-default entity manager, specify its name in the `nbgrp_onelogin_saml.entity_manager_name`
bundle configuration parameter.

User persistence is performing by the event
listeners `Nbgrp\OneloginSamlBundle\EventListener\User\UserCreatedListener`
and `Nbgrp\OneloginSamlBundle\EventListener\User\UserModifiedListener` that can be decorated if you
need to override the default behavior.

Also, you can make your own listeners for `Nbgrp\OneloginSamlBundle\Event\UserCreatedEvent`
and `Nbgrp\OneloginSamlBundle\Event\UserModifiedEvent` events:

``` php
<?php

namespace App\Security;

use Nbgrp\OneloginSamlBundle\Event\UserCreatedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: UserCreatedEvent::class, dispatcher: 'security.event_dispatcher.main')]
class UserCreatedListener
{
    public function __invoke(UserCreatedEvent $event): void
    {
        // ...
    }
}

```

**Important:** you must specify the `dispatcher` option corresponding the firewall which will
trigger the event (`main` in the example above). Read more
about [Security Events](https://symfony.com/doc/current/security.html#security-events).
