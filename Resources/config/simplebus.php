<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/** @var ContainerBuilder $container */
$container = $container ?? (function (): ContainerBuilder { throw new \LogicException('Invalid context.'); })();
$handlers = $container->getParameter('kernel.project_dir').'/vendor/msgphp/user/Command/Handler/*Handler.php';

return function (ContainerConfigurator $container) use ($handlers): void {
    $services = $container->services()
        ->defaults()
            ->autowire()
            ->public()
    ;

    foreach (glob($handlers) as $file) {
        $handler = 'MsgPhp\\User\\Command\\Handler\\'.basename($file, '.php');
        $command = 'MsgPhp\\User\\Command\\'.basename($file, 'Handler.php').'Command';

        $services->set($handler)->tag('command_handler', ['handles' => $command]);
    }
};
