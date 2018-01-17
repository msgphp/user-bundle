<?php

declare(strict_types=1);

namespace MsgPhp\UserBundle\DependencyInjection;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use MsgPhp\Domain\CommandBusInterface;
use MsgPhp\Domain\Infra\DependencyInjection\Bundle\{ConfigHelper, ContainerHelper};
use MsgPhp\EavBundle\MsgPhpEavBundle;
use MsgPhp\User\Command\Handler\{AddUserAttributeValueHandler, AddUserRoleHandler, AddUserSecondaryEmailHandler, ChangeUserAttributeValueHandler, ConfirmUserSecondaryEmailHandler, DeleteUserAttributeValueHandler, DeleteUserRoleHandler, DeleteUserSecondaryEmailHandler, MarkUserSecondaryEmailPrimaryHandler, SetUserPendingPrimaryEmailHandler};
use MsgPhp\User\Entity\{User, UserAttributeValue, UserRole, UserSecondaryEmail};
use MsgPhp\User\Infra\Console\Command\{AddUserRoleCommand, DeleteUserRoleCommand};
use MsgPhp\User\Infra\Doctrine\{EntityFieldsMapping, SqlEmailLookup};
use MsgPhp\User\Infra\Doctrine\Repository\{UserAttributeValueRepository, UserRepository, UserRoleRepository, UserSecondaryEmailRepository};
use MsgPhp\User\Infra\Doctrine\Type\UserIdType;
use MsgPhp\User\Infra\Validator\{EmailLookupInterface, ExistingEmailValidator, UniqueEmailValidator};
use MsgPhp\User\Repository\{UserAttributeValueRepositoryInterface, UserRepositoryInterface, UserRoleRepositoryInterface, UserSecondaryEmailRepositoryInterface};
use MsgPhp\User\UserIdInterface;
use SimpleBus\SymfonyBridge\SimpleBusCommandBusBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Console\Application;
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

        ConfigHelper::resolveResolveDataTypeMapping($container, $config['data_type_mapping']);
        ConfigHelper::resolveClassMapping(Configuration::DATA_TYPE_MAP, $config['data_type_mapping'], $config['class_mapping']);

        $loader->load('services.php');

        ContainerHelper::configureIdentityMap($container, $config['class_mapping'], Configuration::IDENTITY_MAP);
        ContainerHelper::configureEntityFactory($container, $config['class_mapping'], Configuration::AGGREGATE_ROOTS);
        ContainerHelper::configureDoctrineOrm($container, self::getDoctrineMappingFiles($container), [EntityFieldsMapping::class]);

        $bundles = ContainerHelper::getBundles($container);

        // persistence infra
        if (isset($bundles[DoctrineBundle::class])) {
            $this->prepareDoctrineBundle($config, $loader, $container);
        }

        // message infra
        if (isset($bundles[SimpleBusCommandBusBundle::class])) {
            $this->prepareSimpleBusCommandBusBundle($config, $loader, $container);
        }

        // framework infra
        if (isset($bundles[FrameworkBundle::class])) {
            $this->prepareFrameworkBundle($config, $loader, $container);
        }

        if (isset($bundles[SecurityBundle::class])) {
            $loader->load('security.php');
        }
    }

    public function prepend(ContainerBuilder $container): void
    {
        $config = $this->processConfiguration($this->getConfiguration($configs = $container->getExtensionConfig($this->getAlias()), $container), $configs);

        ConfigHelper::resolveResolveDataTypeMapping($container, $config['data_type_mapping']);
        ConfigHelper::resolveClassMapping(Configuration::DATA_TYPE_MAP, $config['data_type_mapping'], $config['class_mapping']);

        ContainerHelper::configureDoctrineTypes($container, $config['data_type_mapping'], $config['class_mapping'], [
            UserIdInterface::class => UserIdType::class,
        ]);
        ContainerHelper::configureDoctrineMapping($container, $config['class_mapping']);
    }

    private function prepareDoctrineBundle(array $config, LoaderInterface $loader, ContainerBuilder $container): void
    {
        if (!ContainerHelper::isDoctrineOrmEnabled($container)) {
            return;
        }

        $loader->load('doctrine.php');

        $classMapping = $config['class_mapping'];

        foreach ([
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

        $container->getDefinition(SqlEmailLookup::class)
            ->setArgument('$entityFieldMapping', $entityEmailFieldMapping)
            ->setArgument('$primaryEntityFieldMapping', $primaryEntityEmailFieldMapping);
    }

    private function prepareSimpleBusCommandBusBundle(array $config, LoaderInterface $loader, ContainerBuilder $container): void
    {
        if (!$container->has(UserRepositoryInterface::class)) {
            return;
        }

        $loader->load('simplebus.php');

        $classMapping = $config['class_mapping'];

        if (!isset($classMapping[UserAttributeValue::class]) || !$container->has(UserAttributeValueRepositoryInterface::class)) {
            $container->removeDefinition(AddUserAttributeValueHandler::class);
            $container->removeDefinition(ChangeUserAttributeValueHandler::class);
            $container->removeDefinition(DeleteUserAttributeValueHandler::class);
        }

        if (!isset($classMapping[UserRole::class]) || !$container->has(UserRoleRepositoryInterface::class)) {
            $container->removeDefinition(AddUserRoleHandler::class);
            $container->removeDefinition(DeleteUserRoleHandler::class);
        }

        if (!isset($classMapping[UserSecondaryEmail::class]) || !$container->has(UserSecondaryEmailRepositoryInterface::class)) {
            $container->removeDefinition(AddUserSecondaryEmailHandler::class);
            $container->removeDefinition(ConfirmUserSecondaryEmailHandler::class);
            $container->removeDefinition(DeleteUserSecondaryEmailHandler::class);
            $container->removeDefinition(MarkUserSecondaryEmailPrimaryHandler::class);
            $container->removeDefinition(SetUserPendingPrimaryEmailHandler::class);
        }
    }

    private function prepareFrameworkBundle(array $config, LoaderInterface $loader, ContainerBuilder $container): void
    {
        $classMapping = $config['class_mapping'];

        // console
        // @todo register default EmailLookupInterface from repository implems
        if (class_exists(Application::class) && $container->has(CommandBusInterface::class) && $container->has(UserRepositoryInterface::class) && $container->has(EmailLookupInterface::class)) {
            $loader->load('console.php');

            if (!isset($classMapping[UserRole::class])) {
                $container->removeDefinition(AddUserRoleCommand::class);
                $container->removeDefinition(DeleteUserRoleCommand::class);
            }
        }

        // validator
        if (interface_exists(ValidatorInterface::class)) {
            $loader->load('validator.php');

            if (!$container->has(EmailLookupInterface::class)) {
                $container->removeDefinition(ExistingEmailValidator::class);
                $container->removeDefinition(UniqueEmailValidator::class);
            }
        }
    }

    private static function getDoctrineMappingFiles(ContainerBuilder $container): array
    {
        $files = glob(($baseDir = dirname((new \ReflectionClass(UserIdInterface::class))->getFileName()).'/Infra/Doctrine/Resources/dist-mapping').'/*.orm.xml');

        if (!ContainerHelper::hasBundle($container, MsgPhpEavBundle::class)) {
            $files = array_flip($files);
            unset($files[$baseDir.'/User.Entity.UserAttributeValue.orm.xml']);
            $files = array_values(array_flip($files));
        }

        return $files;
    }
}
