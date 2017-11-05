<?php

use MsgPhp\User\Infra\Doctrine\Repository\{PendingUserRepository, UserRepository, UserAttributeValueRepository, UserRoleRepository, UserSecondaryEmailRepository};
use MsgPhp\User\Infra\Doctrine\SqlEmailLookup;
use MsgPhp\User\Repository\{PendingUserRepositoryInterface, UserRepositoryInterface, UserAttributeValueRepositoryInterface, UserRoleRepositoryInterface, UserSecondaryEmailRepositoryInterface};
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $container) {
    $container->services()
        ->defaults()
            ->autowire()
            ->private()
        ->load('MsgPhp\\User\\Infra\\Doctrine\\Repository\\', '%kernel.project_dir%/vendor/msgphp/user/Infra/Doctrine/Repository')
        ->set(SqlEmailLookup::class)
        ->alias(PendingUserRepositoryInterface::class, PendingUserRepository::class)
        ->alias(UserRepositoryInterface::class, UserRepository::class)
        ->alias(UserAttributeValueRepositoryInterface::class, UserAttributeValueRepository::class)
        ->alias(UserRoleRepositoryInterface::class, UserRoleRepository::class)
        ->alias(UserSecondaryEmailRepositoryInterface::class, UserSecondaryEmailRepository::class)
    ;
};
