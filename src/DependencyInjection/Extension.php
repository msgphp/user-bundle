<?php

declare(strict_types=1);

namespace MsgPhp\UserBundle\DependencyInjection;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use MsgPhp\Domain\CommandBusInterface;
use MsgPhp\Domain\Infra\Bundle\ServiceConfigHelper;
use MsgPhp\Domain\Infra\Uuid\DomainId;
use MsgPhp\User\Command\Handler\{AddUserRoleHandler, AddUserSecondaryEmailHandler, ConfirmPendingUserHandler, ConfirmUserSecondaryEmailHandler, CreatePendingUserHandler, DeleteUserRoleHandler, DeleteUserSecondaryEmailHandler, MarkUserSecondaryEmailPrimaryHandler, SetUserPendingPrimaryEmailHandler};
use MsgPhp\User\Entity\{PendingUser, User, UserAttributeValue, UserRole, UserSecondaryEmail};
use MsgPhp\User\Infra\Console\Command\{AddUserRoleCommand, CreatePendingUserCommand, DeleteUserRoleCommand};
use MsgPhp\User\Infra\Doctrine\Repository\{PendingUserRepository, UserAttributeValueRepository, UserRepository, UserRoleRepository, UserSecondaryEmailRepository};
use MsgPhp\User\Infra\Doctrine\SqlEmailLookup;
use MsgPhp\User\Infra\Doctrine\Type\UserIdType;
use MsgPhp\User\Infra\Security\NativeBcryptPasswordEncoder;
use MsgPhp\User\PasswordEncoderInterface;
use MsgPhp\User\Repository\UserRepositoryInterface;
use MsgPhp\User\UserIdInterface;
use Ramsey\Uuid\Uuid;
use SimpleBus\SymfonyBridge\{SimpleBusCommandBusBundle, SimpleBusEventBusBundle};
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\LoaderInterface;
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
    public const ALIAS = 'msgphp_user';

    public function getAlias(): string
    {
        return self::ALIAS;
    }

    public function getConfiguration(array $config, ContainerBuilder $container): ConfigurationInterface
    {
        return new Configuration();
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new PhpFileLoader($container, new FileLocator(dirname(__DIR__).'/Resources/config'));
        $config = $this->processConfiguration($this->getConfiguration($configs, $container), $configs);
        $bundles = array_flip($container->getParameter('kernel.bundles'));
        $classMapping = $config['class_mapping'];

        ServiceConfigHelper::configureEntityFactory($container, $classMapping, [
            User::class => UserIdInterface::class,
        ]);

        if (isset($bundles[DoctrineBundle::class])) {
            $this->prepareDoctrineBundle($config, $loader, $container);
        }

        if (!$container->has(UserRepositoryInterface::class)) {
            return;
        }

        if (isset($bundles[SimpleBusCommandBusBundle::class])) {
            ServiceConfigHelper::configureSimpleCommandBus($container);

            $this->prepareSimpleBusCommandBusBundle($config, $loader, $container);
        }

        if (isset($bundles[SimpleBusEventBusBundle::class])) {
            ServiceConfigHelper::configureSimpleEventBus($container);
        }

        if (isset($bundles[FrameworkBundle::class])) {
            $this->prepareFrameworkBundle($config, $loader, $container);
        }

        if (isset($bundles[SecurityBundle::class])) {
            $loader->load('security.php');
        }

        if (isset($bundles[TwigBundle::class])) {
            $loader->load('twig.php');
        }

        if (!$container->has(PasswordEncoderInterface::class)) {
            $container->register(NativeBcryptPasswordEncoder::class);
            $container->setAlias(PasswordEncoderInterface::class, new Alias(NativeBcryptPasswordEncoder::class, false));
        }
    }

    public function prepend(ContainerBuilder $container): void
    {
        $config = $this->processConfiguration($this->getConfiguration($configs = $container->getExtensionConfig($this->getAlias()), $container), $configs);
        $bundles = array_flip($container->getParameter('kernel.bundles'));
        $classMapping = $config['class_mapping'];

        if (isset($bundles[DoctrineBundle::class])) {
            $container->prependExtensionConfig('doctrine', [
                'orm' => [
                    'resolve_target_entities' => $classMapping,
                    'mappings' => [
                        'msgphp_user' => [
                            'dir' => '%kernel.project_dir%/vendor/msgphp/user/Infra/Doctrine/Resources/mapping',
                            'type' => 'xml',
                            'prefix' => 'MsgPhp\\User\\Entity',
                            'is_bundle' => false,
                        ],
                    ],
                ],
            ]);

            if (class_exists(Uuid::class)) {
                $types = [];
                if (is_subclass_of($classMapping[UserIdInterface::class], DomainId::class)) {
                    $types[UserIdType::NAME] = UserIdType::class;
                }

                if ($types) {
                    $container->prependExtensionConfig('doctrine', [
                        'dbal' => [
                            'types' => $types,
                        ],
                    ]);
                }
            }
        }
    }

    private function prepareFrameworkBundle(array $config, LoaderInterface $loader, ContainerBuilder $container): void
    {
        $classMapping = $config['class_mapping'];

        if (interface_exists(ValidatorInterface::class)) {
            $loader->load('validator.php');
        }

        if (class_exists(Application::class) && $container->has(CommandBusInterface::class)) {
            $loader->load('console.php');

            if (!isset($classMapping[PendingUser::class])) {
                $container->removeDefinition(CreatePendingUserCommand::class);
            }

            if (!isset($classMapping[UserRole::class])) {
                $container->removeDefinition(AddUserRoleCommand::class);
                $container->removeDefinition(DeleteUserRoleCommand::class);
            }
        }
    }

    private function prepareDoctrineBundle(array $config, LoaderInterface $loader, ContainerBuilder $container): void
    {
        $loader->load('doctrine.php');

        $classMapping = $config['class_mapping'];

        foreach ([
            PendingUserRepository::class => $classMapping[PendingUser::class] ?? null,
            UserRepository::class => $classMapping[User::class],
            UserAttributeValueRepository::class => $classMapping[UserAttributeValue::class] ?? null,
            UserRoleRepository::class => $classMapping[UserRole::class] ?? null,
            UserSecondaryEmailRepository::class => $classMapping[UserSecondaryEmail::class] ?? null,
        ] as $repository => $class) {
            if (null === $class) {
                $container->removeDefinition($repository);
            } else {
                $container->getDefinition($repository)->setArgument('$class', $class);
            }
        }

        $entityEmailFieldMapping = $primaryEntityEmailFieldMapping = [
            $classMapping[User::class] => 'email',
        ];

        if (isset($classMapping[UserSecondaryEmail::class])) {
            $entityEmailFieldMapping[$classMapping[UserSecondaryEmail::class]] = 'email';
        }

        if (isset($classMapping[PendingUser::class])) {
            $entityEmailFieldMapping[$classMapping[PendingUser::class]] = 'email';
        }

        $container->getDefinition(SqlEmailLookup::class)
            ->setArgument('$entityFieldMapping', $entityEmailFieldMapping)
            ->setArgument('$primaryEntityFieldMapping', $primaryEntityEmailFieldMapping);
    }

    private function prepareSimpleBusCommandBusBundle(array $config, LoaderInterface $loader, ContainerBuilder $container): void
    {
        $loader->load('simplebus.php');

        $classMapping = $config['class_mapping'];

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
}
