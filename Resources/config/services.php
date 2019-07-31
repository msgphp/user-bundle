<?php

declare(strict_types=1);

use MsgPhp\User\Role\ChainRoleProvider;
use MsgPhp\User\Role\RoleProvider;
use MsgPhp\UserBundle\Maker;
use Symfony\Bundle\MakerBundle\MakerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
            ->autowire()
            ->autoconfigure()
            ->private()

        ->set(ChainRoleProvider::class)
        ->alias(RoleProvider::class, ChainRoleProvider::class)
    ;

    if (interface_exists(MakerInterface::class)) {
        $services->set(Maker\UserMaker::class, Maker\UserMaker::class)
            ->arg('$classMapping', '%msgphp.domain.class_mapping%')
            ->arg('$projectDir', '%kernel.project_dir%')
            ->tag('maker.command')
        ;
    }
};
