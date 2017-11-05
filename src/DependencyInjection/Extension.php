<?php

declare(strict_types=1);

namespace MsgPhp\UserBundle\DependencyInjection;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use MsgPhp\Domain\Infra\Bundle\ServiceConfigHelper;
use MsgPhp\Eav\{AttributeIdInterface, AttributeValueIdInterface};
use MsgPhp\Eav\Entity\{Attribute, AttributeValue};
use MsgPhp\Eav\Infra\Uuid\AttributeValueId;
use MsgPhp\User\Command\Handler\{AddUserRoleHandler, AddUserSecondaryEmailHandler, ConfirmPendingUserHandler, ConfirmUserSecondaryEmailHandler, CreatePendingUserHandler, DeleteUserRoleHandler, DeleteUserSecondaryEmailHandler, MarkUserSecondaryEmailPrimaryHandler, SetUserPendingPrimaryEmailHandler};
use MsgPhp\User\Entity\{PendingUser, User, UserAttributeValue, UserRole, UserSecondaryEmail};
use MsgPhp\User\Infra\Console\Command\{AddUserRoleCommand, CreatePendingUserCommand, DeleteUserRoleCommand};
use MsgPhp\User\Infra\Doctrine\Repository\{PendingUserRepository, UserRepository, UserRoleRepository, UserSecondaryEmailRepository};
use MsgPhp\User\Infra\Doctrine\SqlEmailLookup;
use MsgPhp\User\Infra\Uuid\UserId;
use MsgPhp\User\Infra\Validator\EmailLookupInterface;
use MsgPhp\User\UserIdInterface;
use SimpleBus\SymfonyBridge\SimpleBusCommandBusBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Console\Application;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension as BaseExtension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @author Roland Franssen <franssen.roland@gmail.com>
 */
final class Extension extends BaseExtension
{
    public function getAlias(): string
    {
        return 'msgphp_user';
    }

    public function getConfiguration(array $config, ContainerBuilder $container): ConfigurationInterface
    {
        return new Configuration();
    }

    public function load(array $configs, ContainerBuilder $container)
    {
        $config = $this->processConfiguration($this->getConfiguration($configs, $container), $configs);
        $classMapping = $config['class_mapping'];

        $loader = new PhpFileLoader($container, new FileLocator(dirname(__DIR__).'/Resources/config'));
        $bundles = array_flip($container->getParameter('kernel.bundles'));

        ServiceConfigHelper::configureEntityFactory($container, $classMapping, [
            Attribute::class => AttributeIdInterface::class,
            AttributeValue::class => AttributeValueIdInterface::class,
            User::class => UserIdInterface::class,
            UserAttributeValue::class => AttributeValueIdInterface::class,
        ]);

        if (isset($bundles[FrameworkBundle::class]) && class_exists(Application::class)) {
            $loader->load('console.php');

            if (!isset($classMapping[PendingUser::class])) {
                $container->removeDefinition(CreatePendingUserCommand::class);
            }

            if (!isset($classMapping[UserRole::class])) {
                $container->removeDefinition(AddUserRoleCommand::class);
                $container->removeDefinition(DeleteUserRoleCommand::class);
            }
        }

        if (isset($bundles[DoctrineBundle::class])) {
            $loader->load('doctrine.php');

            foreach ([
                PendingUserRepository::class => $classMapping[PendingUser::class] ?? null,
                UserRepository::class => $classMapping[User::class] ?? null,
                UserRoleRepository::class => $classMapping[UserRole::class] ?? null,
                UserSecondaryEmailRepository::class => $classMapping[UserSecondaryEmail::class] ?? null,
            ] as $repository => $class) {
                if (null === $class) {
                    $container->removeDefinition($repository);
                    foreach ($container->getAliases() as $id => $alias) {
                        if ((string) $alias === $repository) {
                            $container->removeAlias($id);
                        }
                    }
                } else {
                    $container->getDefinition($repository)->setArgument('$class', $class);
                }
            }

            $container->getDefinition(SqlEmailLookup::class)
                ->setArgument('$primaryEntity', $classMapping[User::class])
                ->setArgument('$subEntities', array_filter([$classMapping[PendingUser::class] ?? null, $classMapping[UserSecondaryEmail::class] ?? null]))
            ;
        }

        if (isset($bundles[SecurityBundle::class])) {
            $loader->load('security.php');
        }

        if (isset($bundles[SimpleBusCommandBusBundle::class])) {
            ServiceConfigHelper::configureSimpleBus($container);

            $loader->load('simplebus.php');

            if (!isset($classMapping[PendingUser::class])) {
                $container->removeDefinition(ConfirmPendingUserHandler::class);
                $container->removeDefinition(CreatePendingUserHandler::class);
            }

            if (!isset($classMapping[UserRole::class])) {
                $container->removeDefinition(AddUserRoleHandler::class);
                $container->removeDefinition(DeleteUserRoleHandler::class);
            }

            if (!isset($classMapping[UserSecondaryEmail::class])) {
                $container->removeDefinition(AddUserSecondaryEmailHandler::class);
                $container->removeDefinition(ConfirmUserSecondaryEmailHandler::class);
                $container->removeDefinition(DeleteUserSecondaryEmailHandler::class);
                $container->removeDefinition(MarkUserSecondaryEmailPrimaryHandler::class);
                $container->removeDefinition(SetUserPendingPrimaryEmailHandler::class);
            }
        }

        if (isset($bundles[TwigBundle::class])) {
            $loader->load('twig.php');
        }

        if (isset($bundles[FrameworkBundle::class]) && interface_exists(ValidatorInterface::class)) {
            $loader->load('validator.php');

            if (!$container->has(EmailLookupInterface::class) && $container->has(SqlEmailLookup::class)) {
                $container->setAlias(EmailLookupInterface::class, new Alias(SqlEmailLookup::class, false));
            }
        }
    }
}
