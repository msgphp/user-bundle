<?php

declare(strict_types=1);

namespace MsgPhp\UserBundle\DependencyInjection;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use MsgPhp\Domain\Factory\EntityFactoryInterface;
use MsgPhp\Domain\Infra\DependencyInjection\Bundle\{ConfigHelper, ContainerHelper};
use MsgPhp\EavBundle\MsgPhpEavBundle;
use MsgPhp\User\UserIdInterface;
use MsgPhp\User\Entity\{User, Username, UserAttributeValue, UserRole, UserSecondaryEmail};
use MsgPhp\User\Infra\Doctrine\EntityFieldsMapping;
use MsgPhp\User\Infra\Doctrine\Event\UsernameListener;
use MsgPhp\User\Infra\Doctrine\Repository\{UserAttributeValueRepository, UserRepository, UsernameRepository, UserRoleRepository, UserSecondaryEmailRepository};
use MsgPhp\User\Infra\Doctrine\Type\UserIdType;
use MsgPhp\User\Infra\{Console as ConsoleInfra, Security as SecurityInfra, Validator as ValidatorInfra};
use MsgPhp\User\Repository\{UserRepositoryInterface, UsernameRepositoryInterface};
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension as BaseExtension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Validator\Validation;

/**
 * @author Roland Franssen <franssen.roland@gmail.com>
 */
final class Extension extends BaseExtension implements PrependExtensionInterface, CompilerPassInterface
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
        ContainerHelper::configureDoctrineOrmMapping($container, self::getDoctrineMappingFiles($config, $container), [EntityFieldsMapping::class]);

        $bundles = ContainerHelper::getBundles($container);

        // persistence infra
        if (isset($bundles[DoctrineBundle::class])) {
            $this->prepareDoctrineBundle($config, $loader, $container);
        }

        // framework infra
        if (class_exists(Security::class)) {
            $loader->load('security.php');
        }

        if (class_exists(Validation::class)) {
            $loader->load('validator.php');

            if (!$container->has(UserRepositoryInterface::class)) {
                $container->removeDefinition(ValidatorInfra\ExistingUsernameValidator::class);
                $container->removeDefinition(ValidatorInfra\UniqueUsernameValidator::class);
            }
        }

        if (class_exists(ConsoleEvents::class)) {
            $loader->load('console.php');

            if (!$container->has(UsernameRepositoryInterface::class)) {
                $container->removeDefinition(ConsoleInfra\Command\SynchronizeUsernamesCommand::class);
            }
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
        ContainerHelper::configureDoctrineOrmTargetEntities($container, $config['class_mapping']);
    }

    public function process(ContainerBuilder $container): void
    {
        if ($container->hasDefinition('data_collector.security')) {
            $container->getDefinition('data_collector.security')
                ->setClass(SecurityInfra\DataCollector::class)
                ->setArgument('$repository', new Reference(UserRepositoryInterface::class, ContainerBuilder::NULL_ON_INVALID_REFERENCE))
                ->setArgument('$factory', new Reference(EntityFactoryInterface::class, ContainerBuilder::NULL_ON_INVALID_REFERENCE));
        }
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
            UsernameRepository::class => $config['username_lookup'] ? Username::class : null,
            UserAttributeValueRepository::class => $classMapping[UserAttributeValue::class] ?? null,
            UserRoleRepository::class => $classMapping[UserRole::class] ?? null,
            UserSecondaryEmailRepository::class => $classMapping[UserSecondaryEmail::class] ?? null,
        ] as $repository => $class) {
            if (null === $class) {
                ContainerHelper::removeDefinitionWithAliases($container, $repository);
                continue;
            }

            ($definition = $container->getDefinition($repository))
                ->setArgument('$class', $class);

            if (UserRepository::class === $repository && null !== $config['username_field']) {
                $definition->setArgument('$fieldMapping', ['username' => $config['username_field']]);
            }

            if (UsernameRepository::class === $repository) {
                $definition->setArgument('$targetMapping', $config['username_lookup']);
            }
        }

        if ($config['username_lookup']) {
            $container->getDefinition(UsernameListener::class)
                ->setArgument('$mapping', $config['username_lookup']);
        } else {
            $container->removeDefinition(UsernameListener::class);
        }
    }

    private static function getDoctrineMappingFiles(array $config, ContainerBuilder $container): array
    {
        $files = glob(($baseDir = dirname((new \ReflectionClass(UserIdInterface::class))->getFileName()).'/Infra/Doctrine/Resources/dist-mapping').'/*.orm.xml');
        $files = array_flip($files);

        if (!ContainerHelper::hasBundle($container, MsgPhpEavBundle::class)) {
            unset($files[$baseDir.'/User.Entity.UserAttributeValue.orm.xml']);
        }

        if (!$config['username_lookup']) {
            unset($files[$baseDir.'/User.Entity.Username.orm.xml']);
        }

        return array_values(array_flip($files));
    }
}
