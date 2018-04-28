<?php

declare(strict_types=1);

use MsgPhp\UserBundle\DependencyInjection\Configuration;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $container): void {
    $container->services()
        ->defaults()
            ->autowire()
            ->private()

        ->load($ns = 'MsgPhp\\User\\Command\\Handler\\', Configuration::getPackageDir().'/Command/Handler/*Handler.php')
            ->tag('msgphp.domain.command_handler')
    ;
};
