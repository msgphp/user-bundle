# User Bundle

A new Symfony bundle for basic user management.

## Installation

```bash
composer require msgphp/user-bundle
```

## Features

- Symfony 3.4 / 4.0 ready
- Doctrine persistence
- Symfony console commands
- Symfony security infrastructure
- Symfony validators
- Credential independent (supports e-mail, nickname, etc.)
- Disabled / enabled users
- User roles
- User attribute values
- User secondary e-mails

## Blog posts

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

### With `doctrine/orm`

Repositories from `MsgPhp\User\Infra\Doctrine\Repository\*` are registered as a service. Corresponding domain interfaces
from  `MsgPhp\User\Repository\*` are aliased.

Minimal `DoctrineBundle` configuration:

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

### With `simple-bus/message-bus`

Command handlers from `MsgPhp\User\Command\*` are registered as a service.

- Suggests `simple-bus/symfony-bridge` to setup a standard command and event bus

### With `symfony/security-core`

Security infrastructure from `MsgPhp\User\Infra\Security\*` is registered as a service.

- Suggests `sensio/framework-extra-bundle` to enable the parameter converter

In practice the security user is decoupled from your domain entity user. An approach described
[here](https://stovepipe.systems/post/decoupling-your-security-user).

- `MsgPhp\User\Infra\Security\SecurityUser` implementing `Symfony\Component\Security\Core\User\UserInterface`
- `App\Entity\User\User` extending `MsgPhp\User\Entity\User`

Minimal `SecurityBundle` configuration:

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

- Requires `doctrine/orm` or a `MsgPhp\User\Repository\UserRepositoryInterface` service/alias

### With `symfony/console`

Console commands from `MsgPhp\User\Infra\Console\Command\*` are registered as a service.

### With `symfony/validator`

Validators from `MsgPhp\User\Infra\Validator\*` are registered as a service.

## Documentation

- Read the [main documentation](https://msgphp.github.io/docs/)
- Browse the [API documentation](https://msgphp.github.io/api/MsgPhp/UserBundle.html)
- Try the Symfony [demo application](https://github.com/msgphp/symfony-demo-app)

## Contributing

This repository is **READ ONLY**. Issues and pull requests should be submitted in the
[main development repository](https://github.com/msgphp/msgphp).
