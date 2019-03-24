<?php

declare(strict_types=1);

use MsgPhp\User\Infrastructure\Form;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $container): void {
    $container->services()
        ->defaults()
            ->autowire()
            ->autoconfigure()
            ->private()

        ->set(Form\Type\HashedPasswordType::class)
    ;
};
