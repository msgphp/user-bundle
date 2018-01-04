<?php

declare(strict_types=1);

namespace MsgPhp;

use MsgPhp\Domain\Infra\DependencyInjection\Bundle\ContainerHelper;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/** @var ContainerBuilder $container */
$container = $container ?? (function (): ContainerBuilder { throw new \LogicException('Invalid context.'); })();
$reflector = ContainerHelper::getClassReflector($container);
$pattern = '%kernel.project_dir%/vendor/msgphp/user/Command/Handler/*Handler.php';
$handlers = $container->getParameterBag()->resolveValue($pattern);

return function (ContainerConfigurator $container) use ($reflector, $handlers, $pattern): void {
    $services = $container->services()
        ->defaults()
            ->autowire()
            ->public()

        ->load($ns = 'MsgPhp\\User\\Command\\Handler\\', $pattern)
    ;

    foreach (glob($handlers) as $file) {
        $services->get($handler = $ns.basename($file, '.php'))->tag('command_handler', [
            'handles' => $reflector($handler)->getMethod('handle')->getParameters()[0]->getClass()->getName(),
        ]);
    }
};
