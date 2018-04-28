<?php

declare(strict_types=1);

use MsgPhp\User\UserIdInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $container): void {
    $baseDir = dirname((new \ReflectionClass(UserIdInterface::class))->getFileName());
    $container->services()
        ->defaults()
            ->autowire()
            ->private()

        ->load($ns = 'MsgPhp\\User\\Command\\Handler\\', $baseDir.'/Command/Handler/*Handler.php')
            ->tag('msgphp.domain.command_handler')
    ;
};
