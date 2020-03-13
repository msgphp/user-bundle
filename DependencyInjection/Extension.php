<?php

declare(strict_types=1);

namespace MsgPhp\UserBundle\DependencyInjection;

use MsgPhp\Domain\Infrastructure\Console\Definition\ClassContextDefinition as ConsoleClassContextDefinition;
use MsgPhp\Domain\Infrastructure\DependencyInjection\ContainerHelper;
use MsgPhp\Domain\Infrastructure\DependencyInjection\ExtensionHelper;
use MsgPhp\Domain\Infrastructure\DependencyInjection\FeatureDetection;
use MsgPhp\User\Credential\Credential;
use MsgPhp\User\Infrastructure\Console as ConsoleInfrastructure;
use MsgPhp\User\Infrastructure\Doctrine as DoctrineInfrastructure;
use MsgPhp\User\Role;
use MsgPhp\User\User;
use MsgPhp\User\UserRole;
use MsgPhp\UserBundle\Twig;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension as BaseExtension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @author Roland Franssen <franssen.roland@gmail.com>
 *
 * @internal
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
        $loader = new PhpFileLoader($container, new FileLocator(\dirname(__DIR__).'/Resources/config'));
        $config = $this->processConfiguration($this->getConfiguration($configs, $container), $configs);

        ExtensionHelper::configureDomain($container, $config['class_mapping']);

        // default infra
        $loader->load('services.php');

        // message infra
        $loader->load('message.php');
        ExtensionHelper::finalizeCommandHandlers($container, $config['class_mapping'], $config['commands'], Configuration::getPackageMetadata()->getEventClasses());

        // persistence infra
        if (FeatureDetection::isDoctrineOrmAvailable($container)) {
            $this->loadDoctrineOrm($config, $loader, $container);
        }

        // framework infra
        if (FeatureDetection::isConsoleAvailable($container)) {
            $this->loadConsole($config, $loader, $container);
        }

        if (FeatureDetection::isFormAvailable($container)) {
            $loader->load('form.php');
        }

        if (FeatureDetection::isValidatorAvailable($container)) {
            $loader->load('validator.php');
        }

        if (FeatureDetection::hasSecurityBundle($container)) {
            $loader->load('security.php');
        }

        if (FeatureDetection::hasTwigBundle($container)) {
            $loader->load('twig.php');
        }

        $this->configureRoleProvider($config, $container);
    }

    public function prepend(ContainerBuilder $container): void
    {
        $config = $this->processConfiguration($this->getConfiguration($configs = $container->getExtensionConfig($this->getAlias()), $container), $configs);

        if (FeatureDetection::isDoctrineOrmAvailable($container)) {
            ExtensionHelper::configureDoctrineOrm(
                $container,
                $config['class_mapping'],
                $config['id_type_mapping'],
                Configuration::DOCTRINE_TYPE_MAPPING,
                Configuration::getPackageMetadata()->getDoctrineMappingFiles()
            );
        }

        if (FeatureDetection::hasTwigBundle($container)) {
            $container->prependExtensionConfig('twig', [
                'globals' => [
                    Twig\GlobalVariable::NAME => '@'.Twig\GlobalVariable::class,
                ],
            ]);
        }
    }

    private function loadDoctrineOrm(array $config, LoaderInterface $loader, ContainerBuilder $container): void
    {
        $loader->load('doctrine.php');

        $container->getDefinition(DoctrineInfrastructure\Repository\UserRepository::class)
            ->setArgument('$usernameField', $config['username_field'])
        ;

        ExtensionHelper::finalizeDoctrineOrmRepositories($container, $config['class_mapping'], Configuration::DOCTRINE_REPOSITORY_MAPPING);

        if ($config['username_lookup']) {
            $container->getDefinition(DoctrineInfrastructure\UsernameLookup::class)
                ->setArgument('$mapping', $config['username_lookup'])
            ;
        } else {
            $container->removeDefinition(DoctrineInfrastructure\UsernameLookup::class);
        }

        if ($config['username_lookup'] && $config['doctrine']['auto_sync_username']) {
            ($usernameListener = $container->getDefinition(DoctrineInfrastructure\Event\UsernameListener::class))
                ->setArgument('$mapping', $config['username_lookup'])
            ;

            foreach (array_keys($config['username_lookup']) as $entity) {
                $usernameListener
                    ->addTag('doctrine.orm.entity_listener', ['entity' => $entity, 'event' => 'prePersist'])
                    ->addTag('doctrine.orm.entity_listener', ['entity' => $entity, 'event' => 'preUpdate'])
                    ->addTag('doctrine.orm.entity_listener', ['entity' => $entity, 'event' => 'preRemove'])
                ;
            }
        } else {
            $container->removeDefinition(DoctrineInfrastructure\Event\UsernameListener::class);
        }
    }

    private function loadConsole(array $config, LoaderInterface $loader, ContainerBuilder $container): void
    {
        $loader->load('console.php');

        $container->getDefinition(ConsoleInfrastructure\Command\CreateUserCommand::class)
            ->setArgument('$contextDefinition', ExtensionHelper::registerConsoleClassContextDefinition(
                $container,
                $config['class_mapping'][User::class]
            ))
        ;

        if (isset($config['class_mapping'][Role::class])) {
            $container->getDefinition(ConsoleInfrastructure\Command\CreateRoleCommand::class)
                ->setArgument('$contextDefinition', ExtensionHelper::registerConsoleClassContextDefinition(
                    $container,
                    $config['class_mapping'][Role::class]
                ))
            ;
        }

        if (isset($config['class_mapping'][UserRole::class])) {
            $container->getDefinition(ConsoleInfrastructure\Command\AddUserRoleCommand::class)
                ->setArgument('$contextDefinition', ExtensionHelper::registerConsoleClassContextDefinition(
                    $container,
                    $config['class_mapping'][UserRole::class],
                    ConsoleClassContextDefinition::REUSE_DEFINITION
                ))
            ;
        }

        if (isset($config['username_field'])) {
            $container->getDefinition(ConsoleInfrastructure\Command\ChangeUserCredentialCommand::class)
                ->setArgument('$contextDefinition', ExtensionHelper::registerConsoleClassContextDefinition(
                    $container,
                    $config['class_mapping'][Credential::class],
                    ConsoleClassContextDefinition::ALWAYS_OPTIONAL | ConsoleClassContextDefinition::NO_DEFAULTS
                ))
            ;
        }

        ExtensionHelper::finalizeConsoleCommands($container, $config['commands'], Configuration::CONSOLE_COMMAND_MAPPING);
    }

    private function configureRoleProvider(array $config, ContainerBuilder $container): void
    {
        if (isset($config['class_mapping'][UserRole::class])) {
            $userProvider = $container->autowire(Role\UserRoleProvider::class);
            $userProvider->setPublic(false);
        }

        $providers = [];
        foreach ($config['role_providers'] as $factory => $provider) {
            if ('default' === $factory) {
                if (!$provider) {
                    continue;
                }

                $defaultProvider = ContainerHelper::registerAnonymous($container, Role\DefaultRoleProvider::class, false, $defaultProviderId);
                $defaultProvider->setArgument('$roles', $provider);
                $provider = $defaultProviderId;
            }

            /** @var string $provider */
            $providers[] = new Reference($provider);
        }

        $container->getDefinition(Role\ChainRoleProvider::class)
            ->setArgument('$providers', new IteratorArgument($providers))
        ;
    }
}
