<?php

declare(strict_types=1);

use MsgPhp\User\UserIdInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $container): void {
    $baseDir = dirname((new \ReflectionClass(UserIdInterface::class))->getFileName());
    $container->services()
        ->defaults()
            ->autowire()
            ->autoconfigure()
            ->private()

        ->load('MsgPhp\\User\\Infra\\Console\\Command\\', $baseDir.'/Infra/Console/Command/*Command.php')
    ;
};
