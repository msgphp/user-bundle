# User bundle

A new Symfony bundle for basic user management.

[![Latest Stable Version](https://poser.pugx.org/msgphp/user-bundle/v/stable)](https://packagist.org/packages/msgphp/user-bundle)

This package is part of the _Message driven PHP_ project.

> [MsgPHP](https://msgphp.github.io/) is a project that aims to provide (common) message based domain layers for your application. It has a low development time overhead and avoids being overly opinionated.

## Installation

```bash
composer require msgphp/user-bundle
```

## Features

- Symfony 3.4 / 4.0 ready
- Doctrine persistence (with built-in discriminator support)
- Symfony console commands
- Symfony security infrastructure
- Symfony validators
- Credential independent (supports e-mail, nickname, etc.)
- Multiple username / credential support
- Disabled / enabled users
- User roles
- User attribute values
- User secondary e-mails

## Blog posts

- [Commanding a decoupled User entity](https://medium.com/@ro0NL/commanding-a-decoupled-user-entity-aee8723c43e5)
- [Decoupling the User entity with a new Symfony User Bundle](https://medium.com/@ro0NL/decoupling-the-user-entity-with-a-new-symfony-user-bundle-7d2d5d85bdf9)
- [Building a new Symfony User Bundle](https://medium.com/@ro0NL/building-a-new-symfony-user-bundle-b4fe5a9d9d80)

## Configuration

```php
<?php
// config/packages/msgphp.php

use MsgPhp\User\Entity\User;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $container) {
    $container->extension('msgphp_user', [
        'class_mapping' => [
            User::class => \App\Entity\User\User::class,
        ],
    ]);
};
```

And be done.

## Usage

### With [`DoctrineBundle`](https://github.com/doctrine/DoctrineBundle)

Repositories from `MsgPhp\User\Infra\Doctrine\Repository\*` are registered as a service. Corresponding domain interfaces
from  `MsgPhp\User\Repository\*` are aliased.

Minimal configuration:

```yaml
# config/packages/doctrine.yaml

doctrine:
    orm:
        mappings:
            app:
                dir: '%kernel.project_dir%/src/Entity'
                type: annotation
                prefix: App\Entity
```

- Requires `doctrine/orm`

### With [`SimpleBusCommandBusBundle`](https://github.com/SimpleBus/SymfonyBridge)

Command handlers from `MsgPhp\User\Command\*` are registered as a service.

- Requires `DoctrineBundle + doctrine/orm`

### With [`SecurityBundle`](https://github.com/symfony/security-bundle)

Security infrastructure from `MsgPhp\User\Infra\Security\*` is registered as a service.

In practice the security user is decoupled from your domain entity user. An approach described
[here](https://stovepipe.systems/post/decoupling-your-security-user).

- `MsgPhp\User\Infra\Security\SecurityUser` implementing `Symfony\Component\Security\Core\User\UserInterface`
- `App\Entity\User\User` extending `MsgPhp\User\Entity\User`

Minimal configuration:

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

- Requires `DoctrineBundle + doctrine/orm`
- Suggests `SensioFrameworkExtraBundle` to enable the parameter converter

### With [`symfony/console`](https://github.com/symfony/console)

Console commands from `MsgPhp\User\Infra\Console\Command\*` are registered as a service.

- Requires `DoctrineBundle + doctrine/orm`
- Requires `SimpleBusCommandBusBundle`

### With [`symfony/form`](https://github.com/symfony/form)

Form types from `MsgPhp\User\Infra\Form\Type\*` are registered as a service.

### With [`symfony/validator`](https://github.com/symfony/validator)

Validators from `MsgPhp\User\Infra\Validator\*` are registered as a service.

- Requires `DoctrineBundle + doctrine/orm`

## Documentation

- Read the [main documentation](https://msgphp.github.io/docs/)
- Browse the [API documentation](https://msgphp.github.io/api/MsgPhp/UserBundle.html)
- Try the Symfony [demo application](https://github.com/msgphp/symfony-demo-app)

## Contributing

This repository is **READ ONLY**. Issues and pull requests should be submitted in the
[main development repository](https://github.com/msgphp/msgphp).
