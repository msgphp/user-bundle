<?php

declare(strict_types=1);

namespace MsgPhp\UserBundle\DependencyInjection;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use MsgPhp\User\Command\Handler\{AddUserRoleHandler, AddUserSecondaryEmailHandler, ConfirmPendingUserHandler, ConfirmUserSecondaryEmailHandler, CreatePendingUserHandler, DeleteUserRoleHandler, DeleteUserSecondaryEmailHandler, MarkUserSecondaryEmailPrimaryHandler, SetUserPendingPrimaryEmailHandler};
use MsgPhp\User\Entity\{PendingUser, User, UserRole, UserSecondaryEmail};
use MsgPhp\User\Infra\Console\Command\{AddUserRoleCommand, CreatePendingUserCommand, DeleteUserRoleCommand};
use MsgPhp\User\Infra\Doctrine\Repository\{PendingUserRepository, UserRepository, UserRoleRepository, UserSecondaryEmailRepository};
use MsgPhp\User\Infra\Doctrine\SqlEmailLookup;
use MsgPhp\User\Infra\Doctrine\Type\UserIdType;
use MsgPhp\User\Infra\Validator\EmailLookupInterface;
use MsgPhp\User\UserFactory;
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
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @author Roland Franssen <franssen.roland@gmail.com>
 */
final class Extension extends BaseExtension implements PrependExtensionInterface
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
        $loader = new PhpFileLoader($container, new FileLocator(dirname(__DIR__).'/Resources/config'));
        $bundles = array_flip($container->getParameter('kernel.bundles'));

        $loader->load('domain.php');

        $container->getDefinition(UserFactory::class)
            ->setArgument('$classMapping', [
                PendingUser::class => $config['pending_user_class'],
                User::class => $config['user_class'],
                UserIdInterface::class => $config['user_id_class'],
                UserRole::class => $config['user_role_class'],
                UserSecondaryEmail::class => $config['user_secondary_email_class'],
            ])
        ;

        if (isset($bundles[FrameworkBundle::class]) && class_exists(Application::class)) {
            $loader->load('console.php');

            if (null === $config['pending_user_class']) {
                $container->removeDefinition(CreatePendingUserCommand::class);
            }

            if (null === $config['user_role_class']) {
                $container->removeDefinition(AddUserRoleCommand::class);
                $container->removeDefinition(DeleteUserRoleCommand::class);
            }
        }

        if (isset($bundles[DoctrineBundle::class])) {
            $loader->load('doctrine.php');

            foreach ([
                PendingUserRepository::class => $config['pending_user_class'],
                UserRepository::class => $config['user_class'],
                UserRoleRepository::class => $config['user_role_class'],
                UserSecondaryEmailRepository::class => $config['user_secondary_email_class'],
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
                ->setArgument('$primaryEntity', $config['user_class'])
                ->setArgument('$subEntities', array_filter([$config['pending_user_class'], $config['user_secondary_email_class']]))
            ;
        }

        if (isset($bundles[SecurityBundle::class])) {
            $loader->load('security.php');
        }

        if (isset($bundles[SimpleBusCommandBusBundle::class])) {
            $loader->load('simplebus.php');

            if (null === $config['pending_user_class']) {
                $container->removeDefinition(ConfirmPendingUserHandler::class);
                $container->removeDefinition(CreatePendingUserHandler::class);
            }

            if (null === $config['user_role_class']) {
                $container->removeDefinition(AddUserRoleHandler::class);
                $container->removeDefinition(DeleteUserRoleHandler::class);
            }

            if (null === $config['user_secondary_email_class']) {
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

    public function prepend(ContainerBuilder $container)
    {
        $configs = $container->getExtensionConfig($this->getAlias());
        $config = $this->processConfiguration($this->getConfiguration($configs, $container), $configs);
        $bundles = array_flip($container->getParameter('kernel.bundles'));

        if (isset($bundles[DoctrineBundle::class])) {
            $container->prependExtensionConfig('doctrine', [
                'dbal' => [
                    'types' => [
                        UserIdType::NAME => $config['doctrine']['user_id_type_class'],
                    ],
                ],
                'orm' => [
                    'resolve_target_entities' => [
                        User::class => $config['user_class'],
                    ],
                    'mappings' => [
                        'MsgPhp\User\Entity' => [
                            'dir' => dirname(dirname(dirname(__DIR__))).'/user/Infra/Doctrine/Resources/mapping',
                            'type' => 'xml',
                            'prefix' => 'MsgPhp\User\Entity',
                            'is_bundle' => false,
                        ],
                    ],
                ],
            ]);
        }
    }
}
