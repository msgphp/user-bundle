# NOT ACTIVELY SUPPORTED ANY MORE!!

msgphp/* repositories are not actively developed/supported anymore.

**Use in production on your own risks.**

If you want to do some hotfixes - please do PR directly in target repository instead of previous msgphp/msgphp  monorepository

# User Bundle

A new Symfony bundle for basic user management.

[![Latest Stable Version][packagist:img]][packagist]

# Installation

```bash
composer require msgphp/user-bundle
```

# Configuration

```php
<?php
// config/packages/msgphp_user.php

use MsgPhp\User\User;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $container) {
    $container->extension('msgphp_user', [
        'class_mapping' => [
            User::class => \App\Entity\User::class,
        ],
    ]);
};
```

## Feeling Lazy?

```bash
composer require maker --dev
bin/console make:user:msgphp
```

# Documentation

- Read the [bundle documentation](https://msgphp.github.io/docs/cookbook/user-bundle/installation/)
- Try the Symfony [demo application](https://github.com/msgphp/symfony-demo-app)
- Get support on [Symfony's Slack `#msgphp` channel](https://symfony.com/slack-invite) or [raise an issue](https://github.com/msgphp/msgphp/issues/new)

# Contributing

[packagist]: https://packagist.org/packages/msgphp/user-bundle
[packagist:img]: https://img.shields.io/packagist/v/msgphp/user-bundle.svg?style=flat-square
