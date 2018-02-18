<?php

declare(strict_types=1);

namespace MsgPhp\UserBundle\DependencyInjection;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\ORM\Version as DoctrineOrmVersion;
use MsgPhp\Domain\Factory\EntityAwareFactoryInterface;
use MsgPhp\Domain\Infra\Console as BaseConsoleInfra;
use MsgPhp\Domain\Infra\DependencyInjection\Bundle\{ConfigHelper, ContainerHelper};
use MsgPhp\EavBundle\MsgPhpEavBundle;
use MsgPhp\User\{Command, CredentialInterface, Entity, Repository, UserIdInterface};
use MsgPhp\User\Infra\{Console as ConsoleInfra, Doctrine as DoctrineInfra, Security as SecurityInfra, Validator as ValidatorInfra};
use SimpleBus\SymfonyBridge\SimpleBusCommandBusBundle;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
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
use Symfony\Component\Form\Form;
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
                Command\Handler\ChangeUserCredentialHandler::class,
                Command\Handler\ConfirmUserHandler::class,
                Command\Handler\CreateUserHandler::class,
                Command\Handler\DeleteUserHandler::class,
                Command\Handler\DisableUserHandler::class,
                Command\Handler\EnableUserHandler::class,
                Command\Handler\RequestUserPasswordHandler::class,
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

        if (class_exists(Form::class)) {
            $loader->load('form.php');
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

            ContainerHelper::removeIf($container, !$container->has(Command\Handler\ChangeUserCredentialHandler::class), [
                ConsoleInfra\Command\ChangeUserCredentialCommand::class,
            ]);
            ContainerHelper::removeIf($container, !$container->has(Command\Handler\ConfirmUserHandler::class), [
                ConsoleInfra\Command\ConfirmUserCommand::class,
            ]);
            ContainerHelper::removeIf($container, !$container->has(Command\Handler\CreateUserHandler::class), [
                ConsoleInfra\Command\CreateUserCommand::class,
            ]);
            ContainerHelper::removeIf($container, !$container->has(Command\Handler\DeleteUserHandler::class), [
                ConsoleInfra\Command\DeleteUserCommand::class,
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

            $container->getDefinition(BaseConsoleInfra\ContextBuilder\ClassContextBuilder::class)
                ->setArgument('$classMapping', $config['class_mapping']);

            if ($container->hasDefinition(ConsoleInfra\Command\CreateUserCommand::class)) {
                $container->getDefinition(ConsoleInfra\Command\CreateUserCommand::class)
                    ->setArgument('$contextBuilder', ContainerHelper::registerAnonymous($container, BaseConsoleInfra\ContextBuilder\ClassContextBuilder::class, true)
                        ->setArgument('$class', Entity\User::class));
            }

            if ($container->hasDefinition(ConsoleInfra\Command\ChangeUserCredentialCommand::class)) {
                $container->getDefinition(ConsoleInfra\Command\ChangeUserCredentialCommand::class)
                    ->setArgument('$contextBuilder', ContainerHelper::registerAnonymous($container, BaseConsoleInfra\ContextBuilder\ClassContextBuilder::class, true)
                        ->setArgument('$class', CredentialInterface::class)
                        ->setArgument('$flags', BaseConsoleInfra\ContextBuilder\ClassContextBuilder::ALWAYS_OPTIONAL | BaseConsoleInfra\ContextBuilder\ClassContextBuilder::NO_DEFAULTS));
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
        if ($container->hasDefinition('data_collector.security')) {
            $container->getDefinition('data_collector.security')
                ->setClass(SecurityInfra\DataCollector::class)
                ->setArgument('$repository', new Reference(Repository\UserRepositoryInterface::class, ContainerBuilder::NULL_ON_INVALID_REFERENCE))
                ->setArgument('$factory', new Reference(EntityAwareFactoryInterface::class, ContainerBuilder::NULL_ON_INVALID_REFERENCE));
        }
    }

    private function prepareDoctrineOrm(array $config, LoaderInterface $loader, ContainerBuilder $container): void
    {
        $loader->load('doctrine.php');

        if (null !== $config['username_field']) {
            $container->getDefinition(DoctrineInfra\Repository\UserRepository::class)
                ->setArgument('$usernameField', $config['username_field']);
        }

        if ($config['username_lookup']) {
            $container->getDefinition(DoctrineInfra\Event\UsernameListener::class)
                ->setArgument('$mapping', $config['username_lookup']);

            $container->getDefinition(DoctrineInfra\Repository\UsernameRepository::class)
                ->setArgument('$targetMapping', $config['username_lookup']);
        } else {
            $container->removeDefinition(DoctrineInfra\Event\UsernameListener::class);
        }

        ContainerHelper::configureDoctrineOrmRepositories($container, $config['class_mapping'], [
            DoctrineInfra\Repository\UserRepository::class => Entity\User::class,
            DoctrineInfra\Repository\UsernameRepository::class => $config['username_lookup'] ? Entity\Username::class : null,
            DoctrineInfra\Repository\UserAttributeValueRepository::class => Entity\UserAttributeValue::class,
            DoctrineInfra\Repository\UserRoleRepository::class => Entity\UserRole::class,
            DoctrineInfra\Repository\UserSecondaryEmailRepository::class => Entity\UserSecondaryEmail::class,
        ]);
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
