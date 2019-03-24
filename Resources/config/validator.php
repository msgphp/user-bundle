<?php

declare(strict_types=1);

use MsgPhp\User\Infrastructure\Validator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $container): void {
    $container->services()
        ->defaults()
            ->autowire()
            ->autoconfigure()
            ->private()

        ->set(Validator\ExistingUsernameValidator::class)
        ->set(Validator\UniqueUsernameValidator::class)
    ;
};
