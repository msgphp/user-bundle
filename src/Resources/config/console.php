<?php

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $container) {
    $container->services()
        ->defaults()
            ->autowire()
            ->autoconfigure()
            ->private()
        ->load('MsgPhp\\User\\Infra\\Console\\Command\\', dirname(dirname(dirname(dirname(__DIR__)))).'/user/Infra/Console/Command')
    ;
};
