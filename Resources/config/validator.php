<?php

declare(strict_types=1);

use MsgPhp\User\Infra\Validator\ExistingEmailValidator;
use MsgPhp\User\Infra\Validator\UniqueEmailValidator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $container): void {
    $container->services()
        ->defaults()
            ->autowire()
            ->autoconfigure()
            ->private()

        ->set(ExistingEmailValidator::class)
        ->set(UniqueEmailValidator::class)
    ;
};
