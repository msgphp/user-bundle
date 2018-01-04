<?php

declare(strict_types=1);

use MsgPhp\User\Infra\Doctrine\Repository\{PendingUserRepository, UserRepository, UserAttributeValueRepository, UserRoleRepository, UserSecondaryEmailRepository};
use MsgPhp\User\Infra\Doctrine\SqlEmailLookup;
use MsgPhp\User\Infra\Validator\EmailLookupInterface;
use MsgPhp\User\Repository\{PendingUserRepositoryInterface, UserRepositoryInterface, UserAttributeValueRepositoryInterface, UserRoleRepositoryInterface, UserSecondaryEmailRepositoryInterface};
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/** @var ContainerBuilder $container */
$container = $container ?? (function (): ContainerBuilder { throw new \LogicException('Invalid context.'); })();
$repositories = $container->getParameter('kernel.project_dir').'/vendor/msgphp/user/Repository/*RepositoryInterface.php';

return function (ContainerConfigurator $container) use ($repositories): void {
    $container->services()
        ->defaults()
            ->autowire()
            ->private()

        ->load('MsgPhp\\User\\Infra\\Doctrine\\Repository\\', '%kernel.project_dir%/vendor/msgphp/user/Infra/Doctrine/Repository/*Repository.php')
        ->alias(PendingUserRepositoryInterface::class, PendingUserRepository::class)
        ->alias(UserRepositoryInterface::class, UserRepository::class)
        ->alias(UserAttributeValueRepositoryInterface::class, UserAttributeValueRepository::class)
        ->alias(UserRoleRepositoryInterface::class, UserRoleRepository::class)
        ->alias(UserSecondaryEmailRepositoryInterface::class, UserSecondaryEmailRepository::class)

        ->set(SqlEmailLookup::class)
        ->alias(EmailLookupInterface::class, SqlEmailLookup::class)
    ;
};
