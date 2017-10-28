<?php

use MsgPhp\User\Infra\Validator\ExistingEmailValidator;
use MsgPhp\User\Infra\Validator\UniqueEmailValidator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $container) {
    $container->services()
        ->defaults()
            ->autowire()
            ->autoconfigure()
            ->private()
        ->set(ExistingEmailValidator::class)
        ->set(UniqueEmailValidator::class)
    ;
};
