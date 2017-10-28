<?php

use MsgPhp\User\Infra\Doctrine\Repository\{PendingUserRepository, UserRepository, UserRoleRepository, UserSecondaryEmailRepository};
use MsgPhp\User\Infra\Doctrine\SqlEmailLookup;
use MsgPhp\User\Repository\{PendingUserRepositoryInterface, UserRepositoryInterface, UserRoleRepositoryInterface, UserSecondaryEmailRepositoryInterface};
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $container) {
    $container->services()
        ->defaults()
            ->autowire()
            ->private()
        ->load('MsgPhp\\User\\Infra\\Doctrine\\Repository\\', dirname(dirname(dirname(__DIR__))).'/Doctrine/Repository')
        ->set(SqlEmailLookup::class)
        ->alias(PendingUserRepositoryInterface::class, PendingUserRepository::class)
        ->alias(UserRepositoryInterface::class, UserRepository::class)
        ->alias(UserRoleRepositoryInterface::class, UserRoleRepository::class)
        ->alias(UserSecondaryEmailRepositoryInterface::class, UserSecondaryEmailRepository::class)
    ;
};
