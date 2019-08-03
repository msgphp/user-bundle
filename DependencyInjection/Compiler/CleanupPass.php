<?php

declare(strict_types=1);

namespace MsgPhp\UserBundle\DependencyInjection\Compiler;

use MsgPhp\Domain\Infrastructure\DependencyInjection\ContainerHelper;
use MsgPhp\User\Command;
use MsgPhp\User\Infrastructure\Console as ConsoleInfrastructure;
use MsgPhp\User\Infrastructure\Security as SecurityInfrastructure;
use MsgPhp\User\Infrastructure\Validator as ValidatorInfrastructure;
use MsgPhp\User\Repository;
use MsgPhp\User\Role;
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
        ContainerHelper::removeIf($container, !$container->has(Repository\RoleRepository::class), [
            Command\Handler\CreateRoleHandler::class,
            Command\Handler\DeleteRoleHandler::class,
            ConsoleInfrastructure\Command\CreateRoleCommand::class,
            ConsoleInfrastructure\Command\DeleteRoleCommand::class,
        ]);
        ContainerHelper::removeIf($container, !$container->has(Repository\UserRepository::class), [
            Command\Handler\CancelUserPasswordRequestHandler::class,
            Command\Handler\ChangeUserCredentialHandler::class,
            Command\Handler\ConfirmUserHandler::class,
            Command\Handler\CreateUserHandler::class,
            Command\Handler\DeleteUserHandler::class,
            Command\Handler\DisableUserHandler::class,
            Command\Handler\EnableUserHandler::class,
            Command\Handler\RequestUserPasswordHandler::class,
            Command\Handler\ResetUserPasswordHandler::class,
            ConsoleInfrastructure\Command\ChangeUserCredentialCommand::class,
            ConsoleInfrastructure\Command\ConfirmUserCommand::class,
            ConsoleInfrastructure\Command\CreateUserCommand::class,
            ConsoleInfrastructure\Command\DeleteUserCommand::class,
            ConsoleInfrastructure\Command\DisableUserCommand::class,
            ConsoleInfrastructure\Command\EnableUserCommand::class,
            SecurityInfrastructure\Jwt\UserIdentityProvider::class,
            SecurityInfrastructure\UserIdentityProvider::class,
            SecurityInfrastructure\UserParamConverter::class,
            SecurityInfrastructure\UserArgumentValueResolver::class,
            ValidatorInfrastructure\ExistingUsernameValidator::class,
            ValidatorInfrastructure\UniqueUsernameValidator::class,
        ]);
        ContainerHelper::removeIf($container, !$container->has(Repository\UsernameRepository::class), [
            ConsoleInfrastructure\Command\SynchronizeUsernamesCommand::class,
        ]);
        ContainerHelper::removeIf($container, !$container->has(Repository\UserAttributeValueRepository::class), [
            Command\Handler\AddUserAttributeValueHandler::class,
            Command\Handler\ChangeUserAttributeValueHandler::class,
            Command\Handler\DeleteUserAttributeValueHandler::class,
        ]);
        ContainerHelper::removeIf($container, !$container->has(Repository\UserEmailRepository::class), [
            Command\Handler\AddUserEmailHandler::class,
            Command\Handler\DeleteUserEmailHandler::class,
        ]);
        ContainerHelper::removeIf($container, !$container->has(Repository\UserRoleRepository::class), [
            Command\Handler\AddUserRoleHandler::class,
            Command\Handler\DeleteUserRoleHandler::class,
            ConsoleInfrastructure\Command\AddUserRoleCommand::class,
            ConsoleInfrastructure\Command\DeleteUserRoleCommand::class,
            Role\UserRoleProvider::class,
        ]);
    }
}
