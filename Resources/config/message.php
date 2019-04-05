<?php

declare(strict_types=1);

use MsgPhp\UserBundle\DependencyInjection\Configuration;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
            ->autowire()
            ->private()
    ;

    foreach (Configuration::getPackageMetadata()->getMessageServicePrototypes() as $resource => $namespace) {
        $prototype = $services->load($namespace, $resource);
        if (Configuration::PACKAGE_NS.'Command\\Handler\\' === $namespace) {
            $prototype->tag('msgphp.domain.command_handler');
        }
    }
};
