<?php

declare(strict_types=1);

use MsgPhp\UserBundle\DependencyInjection\Configuration;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $container): void {
    $container->services()
        ->defaults()
            ->autowire()
            ->autoconfigure()
            ->private()

        ->load('MsgPhp\\User\\Infra\\Console\\Command\\', Configuration::getPackageGlob().'/Infra/Console/Command/*Command.php')
    ;
};
