<?php

declare(strict_types=1);

namespace MsgPhp;

use MsgPhp\Domain\Infra\DependencyInjection\ContainerHelper;
use MsgPhp\User\UserIdInterface;
use SimpleBus\SymfonyBridge\SimpleBusCommandBusBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/** @var ContainerBuilder $container */
$container = $container ?? (function (): ContainerBuilder { throw new \LogicException('Invalid context.'); })();
$reflector = ContainerHelper::getClassReflector($container);
$simpleCommandBusEnabled = ContainerHelper::hasBundle($container, SimpleBusCommandBusBundle::class);

return function (ContainerConfigurator $container) use ($reflector, $simpleCommandBusEnabled): void {
    $baseDir = dirname($reflector(UserIdInterface::class)->getFileName());
    $services = $container->services()
        ->defaults()
        ->autowire()
        ->public()

        ->load($ns = 'MsgPhp\\User\\Command\\Handler\\', $handlers = $baseDir.'/Command/Handler/*Handler.php')
    ;

    if ($simpleCommandBusEnabled) {
        foreach (glob($handlers) as $file) {
            $services->get($handler = $ns.basename($file, '.php'))->tag('command_handler', [
                'handles' => $reflector($handler)->getMethod('__invoke')->getParameters()[0]->getClass()->getName(),
            ]);
        }
    }
};
