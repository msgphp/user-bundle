<?php

declare(strict_types=1);

use MsgPhp\Domain\Infra\Console;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged;

return function (ContainerConfigurator $container): void {
    $container->services()
        ->defaults()
            ->autowire()
            ->autoconfigure()
            ->private()

        ->load('MsgPhp\\User\\Infra\\Console\\Command\\', '%kernel.project_dir%/vendor/msgphp/user/Infra/Console/Command')

        ->set(Console\ContextBuilder\ClassContextBuilder::class)
            ->abstract()
            ->arg('$method', '__construct')
            ->arg('$elementProviders', tagged('msgphp.console.context_element_provider'))
    ;
};
