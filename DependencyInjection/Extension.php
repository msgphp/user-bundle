<?php

declare(strict_types=1);

namespace MsgPhp\UserBundle\DependencyInjection;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\ORM\Version as DoctrineOrmVersion;
use MsgPhp\Domain\Factory\EntityAwareFactoryInterface;
use MsgPhp\Domain\Infra\Console as BaseConsoleInfra;
use MsgPhp\Domain\Infra\DependencyInjection\Bundle\{ConfigHelper, ContainerHelper};
use MsgPhp\EavBundle\MsgPhpEavBundle;
use MsgPhp\User\{Command, Entity, Repository, UserIdInterface};
use MsgPhp\User\Infra\{Console as ConsoleInfra, Doctrine as DoctrineInfra, Security as SecurityInfra, Validator as ValidatorInfra};
use SimpleBus\SymfonyBridge\SimpleBusCommandBusBundle;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension as BaseExtension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
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
        ConfigHelper::resolveClassMapping(Configuration::DATA_TYPE_MAPPING, $config['data_type_mapping'], $config['class_mapping']);

        $loader->load('services.php');

        ContainerHelper::configureIdentityMapping($container, $config['class_mapping'], Configuration::IDENTITY_MAPPING);
        ContainerHelper::configureEntityFactory($container, $config['class_mapping'], Configuration::AGGREGATE_ROOTS);
        ContainerHelper::configureDoctrineOrmMapping($container, self::getDoctrineMappingFiles($config, $container), [DoctrineInfra\EntityFieldsMapping::class]);

        // persistence infra
        if (class_exists(DoctrineOrmVersion::class) && ContainerHelper::hasBundle($container, DoctrineBundle::class)) {
            $this->prepareDoctrineOrm($config, $loader, $container);
        }

        // message infra
        if (ContainerHelper::hasBundle($container, SimpleBusCommandBusBundle::class)) {
            $loader->load('message.php');

            ContainerHelper::removeIf($container, !$container->has(Repository\UserRepositoryInterface::class), [
                Command\Handler\CreateUserHandler::class,
                Command\Handler\DisableUserHandler::class,
                Command\Handler\EnableUserHandler::class,
            ]);
            ContainerHelper::configureCommandMessages($container, $config['class_mapping'], $config['commands']);
            ContainerHelper::configureEventMessages($container, $config['class_mapping'], array_map(function (string $file): string {
                return 'MsgPhp\\User\\Event\\'.basename($file, '.php');
            }, glob(dirname(ContainerHelper::getClassReflection($container, UserIdInterface::class)->getFileName()).'/Event/*Event.php')));
        }

        // framework infra
        if (ContainerHelper::hasBundle($container, SecurityBundle::class)) {
            $loader->load('security.php');

            ContainerHelper::removeIf($container, !$container->has(Repository\UserRepositoryInterface::class), [
                SecurityInfra\SecurityUserProvider::class,
                SecurityInfra\UserParamConverter::class,
                SecurityInfra\UserValueResolver::class,
            ]);
        }

        if (class_exists(Validation::class)) {
            $loader->load('validator.php');

            ContainerHelper::removeIf($container, !$container->has(Repository\UserRepositoryInterface::class), [
                ValidatorInfra\ExistingUsernameValidator::class,
                ValidatorInfra\UniqueUsernameValidator::class,
            ]);
        }

        if (class_exists(ConsoleEvents::class)) {
            $loader->load('console.php');

            ContainerHelper::removeIf($container, !$container->has(Command\Handler\CreateUserHandler::class), [
                ConsoleInfra\Command\CreateUserCommand::class,
            ]);
            ContainerHelper::removeIf($container, !$container->has(Command\Handler\DisableUserHandler::class), [
                ConsoleInfra\Command\DisableUserCommand::class,
            ]);
            ContainerHelper::removeIf($container, !$container->has(Command\Handler\EnableUserHandler::class), [
                ConsoleInfra\Command\EnableUserCommand::class,
            ]);
            ContainerHelper::removeIf($container, !$container->has(Repository\UsernameRepositoryInterface::class), [
                ConsoleInfra\Command\SynchronizeUsernamesCommand::class,
            ]);

            if ($container->hasDefinition(ConsoleInfra\Command\CreateUserCommand::class)) {
                $container->getDefinition(ConsoleInfra\Command\CreateUserCommand::class)
                    ->setArgument('$contextBuilder', $container->register(uniqid($class = BaseConsoleInfra\ContextBuilder\ClassContextBuilder::class), $class)
                        ->setPublic(false)
                        ->setArgument('$class', $config['class_mapping'][Entity\User::class])
                        ->setArgument('$method', '__construct')
                        ->setArgument('$elementProviders', new TaggedIteratorArgument('msgphp.console.context_element_provider'))
                        ->setArgument('$classMapping', $config['class_mapping']));
            }
        }
    }

    public function prepend(ContainerBuilder $container): void
    {
        $config = $this->processConfiguration($this->getConfiguration($configs = $container->getExtensionConfig($this->getAlias()), $container), $configs);

        ConfigHelper::resolveResolveDataTypeMapping($container, $config['data_type_mapping']);
        ConfigHelper::resolveClassMapping(Configuration::DATA_TYPE_MAPPING, $config['data_type_mapping'], $config['class_mapping']);

        ContainerHelper::configureDoctrineTypes($container, $config['data_type_mapping'], $config['class_mapping'], [
            UserIdInterface::class => DoctrineInfra\Type\UserIdType::class,
        ]);
        ContainerHelper::configureDoctrineOrmTargetEntities($container, $config['class_mapping']);
    }

    public function process(ContainerBuilder $container): void
    {
        if ($container->has('data_collector.security')) {
            $container->findDefinition('data_collector.security')
                ->setClass(SecurityInfra\DataCollector::class)
                ->setArgument('$repository', new Reference(Repository\UserRepositoryInterface::class, ContainerBuilder::NULL_ON_INVALID_REFERENCE))
                ->setArgument('$factory', new Reference(EntityAwareFactoryInterface::class, ContainerBuilder::NULL_ON_INVALID_REFERENCE));
        }
    }

    private function prepareDoctrineOrm(array $config, LoaderInterface $loader, ContainerBuilder $container): void
    {
        $loader->load('doctrine.php');

        $classMapping = $config['class_mapping'];

        foreach ([
            DoctrineInfra\Repository\UserRepository::class => $classMapping[Entity\User::class],
            DoctrineInfra\Repository\UsernameRepository::class => $config['username_lookup'] ? $classMapping[Entity\Username::class] : null,
            DoctrineInfra\Repository\UserAttributeValueRepository::class => $classMapping[Entity\UserAttributeValue::class] ?? null,
            DoctrineInfra\Repository\UserRoleRepository::class => $classMapping[Entity\UserRole::class] ?? null,
            DoctrineInfra\Repository\UserSecondaryEmailRepository::class => $classMapping[Entity\UserSecondaryEmail::class] ?? null,
        ] as $repository => $class) {
            if (null === $class) {
                ContainerHelper::removeDefinitionWithAliases($container, $repository);
                continue;
            }

            ($definition = $container->getDefinition($repository))
                ->setArgument('$class', $class);

            if (DoctrineInfra\Repository\UserRepository::class === $repository && null !== $config['username_field']) {
                $definition->setArgument('$fieldMapping', ['username' => $config['username_field']]);
            }

            if (DoctrineInfra\Repository\UsernameRepository::class === $repository) {
                $definition->setArgument('$targetMapping', $config['username_lookup']);
            }
        }

        if ($config['username_lookup']) {
            $container->getDefinition(DoctrineInfra\Event\UsernameListener::class)
                ->setArgument('$mapping', $config['username_lookup']);
        } else {
            $container->removeDefinition(DoctrineInfra\Event\UsernameListener::class);
        }
    }

    private static function getDoctrineMappingFiles(array $config, ContainerBuilder $container): array
    {
        $baseDir = dirname(ContainerHelper::getClassReflection($container, UserIdInterface::class)->getFileName()).'/Infra/Doctrine/Resources/dist-mapping';
        $files = array_flip(glob($baseDir.'/*.orm.xml'));

        if (!ContainerHelper::hasBundle($container, MsgPhpEavBundle::class)) {
            unset($files[$baseDir.'/User.Entity.UserAttributeValue.orm.xml']);
        }

        if (!$config['username_lookup']) {
            unset($files[$baseDir.'/User.Entity.Username.orm.xml']);
        }

        return array_keys($files);
    }
}
