# User Bundle

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
- Symfony console commands
- Symfony messenger commands & events
- Symfony security infrastructure
- Symfony validators
- Doctrine persistence
- Credential independent (supports e-mail, nickname, etc.)
- Multiple username support
- Primary and secondary user e-mails
- Disabled / enabled users
- User roles
- User attribute values

## Blog Posts

- [Adding user management to your Symfony application](https://medium.com/@ro0NL/adding-user-management-to-your-symfony-application-ceeefe2a2e9)
- [Commanding a decoupled User entity](https://medium.com/@ro0NL/commanding-a-decoupled-user-entity-aee8723c43e5)
- [Decoupling the User entity with a new Symfony User Bundle](https://medium.com/@ro0NL/decoupling-the-user-entity-with-a-new-symfony-user-bundle-7d2d5d85bdf9)
- [Building a new Symfony User Bundle](https://medium.com/@ro0NL/building-a-new-symfony-user-bundle-b4fe5a9d9d80)

## Configuration

```php
<?php
// config/packages/msgphp_user.php

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

### Feeling Lazy?

```bash
composer require annot form validator twig security messenger orm
composer require maker --dev
bin/console make:user:msgphp
```

And be done.

## Documentation

- Read the [main documentation](https://msgphp.github.io/docs/)
- Browse the [API documentation](https://msgphp.github.io/api/MsgPhp/UserBundle.html)
- Try the Symfony [demo application](https://github.com/msgphp/symfony-demo-app)
- Get support on [Symfony's Slack `#msgphp` channel](https://symfony.com/slack-invite) or [raise an issue](https://github.com/msgphp/msgphp/issues/new)

## Contributing

This repository is **READ ONLY**. Issues and pull requests should be submitted in the
[main development repository](https://github.com/msgphp/msgphp).
