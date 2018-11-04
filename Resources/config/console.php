<?php

declare(strict_types=1);

use MsgPhp\UserBundle\DependencyInjection\Configuration;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
            ->autowire()
            ->autoconfigure()
            ->private()
    ;

    foreach (Configuration::getPackageDirs() as $dir) {
        if (is_dir($commandDir = $dir.'/Infra/Console/Command')) {
            $services
                ->load(Configuration::PACKAGE_NS.'Infra\\Console\\Command\\', $commandDir.'/*Command.php')
            ;
        }
    }
};
