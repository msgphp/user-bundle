# User Bundle

A new Symfony bundle for basic user management.

## Features

- Symfony 3.4 / 4.0 ready
- E-mail / password based authentication
- User registration / E-mail confirmation
- Forgot password / Reset password / Change password
- Primary / secondary e-mails
- Disabled / enabled users
- User roles
- User attribute values

## Installation

```bash
composer require msgphp/user-bundle
```

## Configuration

```php
<?php
// config/packages/msgphp.php

use MsgPhp\User\Entity\User;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $container) {
    $container->extension('msgphp_user', [
        'class_mapping' => [
            User::class => \App\Entity\User::class,
        ],
    ]);
};
```

And be done.

### Security configuration

If you use [SecurityBundle](https://github.com/symfony/security-bundle) here's a basic setup;

```yaml
# config/packages/security.yaml

security:
    encoders:
        MsgPhp\User\Infra\Security\SecurityUser: bcrypt

    providers:
         msgphp_user: { id: MsgPhp\User\Infra\Security\SecurityUserProvider }

    firewalls:
        main:
            provider: msgphp_user
            anonymous: ~
```

In practice the security user is decoupled from your domain entity user. An approach described [here](https://stovepipe.systems/post/decoupling-your-security-user).

- `MsgPhp\User\Infra\Security\SecurityUser` implementing `Symfony\Component\Security\Core\User\UserInterface`
- `App\Entity\User` extending `MsgPhp\User\Entity\User`

## Usage

### With `FrameworkBundle` + `symfony/console`

Console commands from `MsgPhp\User\Infra\Console\Command\*` are registered.

```bash
bin/console user:create
```

### With `FrameworkBundle` + `symfony/validator`

Constraint validators from `MsgPhp\User\Infra\Validator\*` are registered.

```php
<?php
// @UnqiueEmail()
private $newEmail;

// @ExistingEmail()
private $currentEmail;
```

### With `SimpleBusCommandBusBundle`

Domain command handlers from `MsgPhp\User\Command\Handler\*` are registered.

```php
<?php
$messageBus->handle(new DeleteUserCommand($user->getId()));
```

With `SimpleBusEventBusBundle` corresponding domain events are dispatched.

### With `TwigBundle`

Twig extensions from `MsgPhp\User\Infra\Twig\*` are registered.

```twig
{% if app.user %} {# the security user: `MsgPhp\User\Infra\Security\SecurityUser` #}
    <p>Hello {{ msgphp_current_user().email }}</p> {# the domain user: `App\Entity\User` #}
{% endif %}
```

### With `DoctrineBundle`

Repositories from `MsgPhp\User\Infra\Doctrine\Repository\*` are registered. Corresponding domain interfaces from
`MsgPhp\User\Repository\*` are aliased.

## Contributing

This repository is **READ ONLY**. Issues and pull requests should be submitted in the [main development repository](https://github.com/msgphp/msgphp).
