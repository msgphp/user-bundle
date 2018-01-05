<?php

declare(strict_types=1);

namespace MsgPhp;

use MsgPhp\User\Infra\Validator\{ExistingEmailValidator, UniqueEmailValidator};
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
