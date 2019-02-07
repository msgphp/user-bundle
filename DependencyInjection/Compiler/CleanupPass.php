<?php

declare(strict_types=1);

namespace MsgPhp\UserBundle\DependencyInjection\Compiler;

use MsgPhp\Domain\Infra\DependencyInjection\ContainerHelper;
use MsgPhp\User\{Command, Repository, Role};
use MsgPhp\User\Infra\{Console as ConsoleInfra, Security as SecurityInfra, Validator as ValidatorInfra};
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @author Roland Franssen <franssen.roland@gmail.com>
 *
 * @internal
 */
final class CleanupPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        ContainerHelper::removeIf($container, !$container->has(Repository\RoleRepositoryInterface::class), [
            Command\Handler\CreateRoleHandler::class,
            Command\Handler\DeleteRoleHandler::class,
            ConsoleInfra\Command\AddUserRoleCommand::class,
            ConsoleInfra\Command\CreateRoleCommand::class,
            ConsoleInfra\Command\DeleteRoleCommand::class,
            ConsoleInfra\Command\DeleteUserRoleCommand::class,
        ]);
        ContainerHelper::removeIf($container, !$container->has(Repository\UserRepositoryInterface::class), [
            Command\Handler\ChangeUserCredentialHandler::class,
            Command\Handler\ConfirmUserHandler::class,
            Command\Handler\CreateUserHandler::class,
            Command\Handler\DeleteUserHandler::class,
            Command\Handler\DisableUserHandler::class,
            Command\Handler\EnableUserHandler::class,
            Command\Handler\RequestUserPasswordHandler::class,
            ConsoleInfra\Command\AddUserRoleCommand::class,
            ConsoleInfra\Command\ChangeUserCredentialCommand::class,
            ConsoleInfra\Command\ConfirmUserCommand::class,
            ConsoleInfra\Command\CreateUserCommand::class,
            ConsoleInfra\Command\DeleteUserCommand::class,
            ConsoleInfra\Command\DeleteUserRoleCommand::class,
            ConsoleInfra\Command\DisableUserCommand::class,
            ConsoleInfra\Command\EnableUserCommand::class,
            SecurityInfra\Jwt\SecurityUserProvider::class,
            SecurityInfra\SecurityUserProvider::class,
            SecurityInfra\UserParamConverter::class,
            SecurityInfra\UserArgumentValueResolver::class,
            ValidatorInfra\ExistingUsernameValidator::class,
            ValidatorInfra\UniqueUsernameValidator::class,
        ]);
        ContainerHelper::removeIf($container, !$container->has(Repository\UsernameRepositoryInterface::class), [
            ConsoleInfra\Command\SynchronizeUsernamesCommand::class,
        ]);
        ContainerHelper::removeIf($container, !$container->has(Repository\UserAttributeValueRepositoryInterface::class), [
            Command\Handler\AddUserAttributeValueHandler::class,
            Command\Handler\ChangeUserAttributeValueHandler::class,
            Command\Handler\DeleteUserAttributeValueHandler::class,
        ]);
        ContainerHelper::removeIf($container, !$container->has(Repository\UserEmailRepositoryInterface::class), [
            Command\Handler\AddUserEmailHandler::class,
            Command\Handler\DeleteUserEmailHandler::class,
        ]);
        ContainerHelper::removeIf($container, !$container->has(Repository\UserRoleRepositoryInterface::class), [
            Command\Handler\AddUserRoleHandler::class,
            Command\Handler\DeleteUserRoleHandler::class,
            ConsoleInfra\Command\AddUserRoleCommand::class,
            ConsoleInfra\Command\DeleteUserRoleCommand::class,
            Role\UserRoleProvider::class,
        ]);
    }
}
