<?php

declare(strict_types=1);

use MsgPhp\User\Password\GenericPasswordHashing;
use MsgPhp\User\Password\PasswordHashingInterface;
use MsgPhp\User\Role\ChainRoleProvider;
use MsgPhp\User\Role\RoleProviderInterface;
use MsgPhp\UserBundle\Maker;
use Symfony\Bundle\MakerBundle\MakerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
            ->autowire()
            ->autoconfigure()
            ->private()

        ->set(GenericPasswordHashing::class)
        ->alias(PasswordHashingInterface::class, GenericPasswordHashing::class)

        ->set(ChainRoleProvider::class)
        ->alias(RoleProviderInterface::class, ChainRoleProvider::class)
    ;

    if (interface_exists(MakerInterface::class)) {
        $services->set(Maker\UserMaker::class, Maker\UserMaker::class)
            ->arg('$classMapping', '%msgphp.domain.class_mapping%')
            ->arg('$projectDir', '%kernel.project_dir%')
            ->tag('maker.command')
        ;
    }
};
