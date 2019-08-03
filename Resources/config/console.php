<?php

declare(strict_types=1);

use MsgPhp\User\Infrastructure\Console\RoleDefinition;
use MsgPhp\UserBundle\DependencyInjection\Configuration;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
            ->autowire()
            ->autoconfigure()
            ->private()

        ->set(RoleDefinition::class)
    ;

    foreach (Configuration::getPackageMetadata()->getConsoleServicePrototypes() as $resource => $namespace) {
        $services->load($namespace, $resource);
    }
};
