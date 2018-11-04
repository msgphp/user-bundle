<?php

declare(strict_types=1);

use MsgPhp\UserBundle\DependencyInjection\Configuration;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
            ->autowire()
            ->private()
    ;

    foreach (Configuration::getPackageDirs() as $dir) {
        $services
            ->load(Configuration::PACKAGE_NS.'Command\\Handler\\', $dir.'/Command/Handler/*Handler.php')
                ->tag('msgphp.domain.command_handler')
        ;
    }
};
