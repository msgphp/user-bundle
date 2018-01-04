<?php

declare(strict_types=1);

namespace MsgPhp;

use MsgPhp\User\Password\{PasswordHashing, PasswordHashingInterface};
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $container): void {
    $container->services()
        ->defaults()
            ->autowire()
            ->autoconfigure()
            ->private()

        ->set(PasswordHashing::class)
        ->alias(PasswordHashingInterface::class, PasswordHashing::class)
    ;
};
