<?php

namespace F;

use MsgPhp\User\UserFactory;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $container) {
    $container->services()
        ->defaults()
            ->autowire()
            ->private()
        ->set(UserFactory::class)
    ;
};
