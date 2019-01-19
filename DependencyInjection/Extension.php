<?php

declare(strict_types=1);

namespace MsgPhp\UserBundle\DependencyInjection;

use MsgPhp\Domain\Infra\Console\Context\ClassContextFactory as ConsoleClassContextFactory;
use MsgPhp\Domain\Infra\DependencyInjection\{ContainerHelper, ExtensionHelper, FeatureDetection};
use MsgPhp\User\{CredentialInterface, Entity, Repository, Role};
use MsgPhp\User\Infra\{Console as ConsoleInfra, Doctrine as DoctrineInfra, Security as SecurityInfra};
use MsgPhp\UserBundle\Twig;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
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
        $loader = new PhpFileLoader($container, new FileLocator(\dirname(__DIR__).'/Resources/config'));
        $config = $this->processConfiguration($this->getConfiguration($configs, $container), $configs);

        ExtensionHelper::configureDomain($container, $config['class_mapping'], Configuration::IDENTITY_MAPPING);

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

    public function process(ContainerBuilder $container): void
    {
        if (FeatureDetection::hasSecurityBundle($container) && $container->hasDefinition($id = 'data_collector.security')) {
            $container->getDefinition($id)
                ->setClass(SecurityInfra\DataCollector::class)
                ->setArgument('$repository', new Reference(Repository\UserRepositoryInterface::class, ContainerBuilder::NULL_ON_INVALID_REFERENCE))
            ;
        }
    }

    private function loadDoctrineOrm(array $config, LoaderInterface $loader, ContainerBuilder $container): void
    {
        $loader->load('doctrine.php');

        $container->getDefinition(DoctrineInfra\Repository\UserRepository::class)
            ->setArgument('$usernameField', $config['username_field'])
            ->setArgument('$usernameClass', $config['username_lookup'] ? $config['class_mapping'][Entity\Username::class] : null)
        ;

        $container->getDefinition(DoctrineInfra\Repository\UsernameRepository::class)
            ->setArgument('$targetMappings', $config['username_lookup'])
            ->addTag('msgphp.domain.process_class_mapping', ['argument' => '$targetMappings', 'array_keys' => true])
        ;

        if ($config['doctrine']['auto_sync_username']) {
            $container->getDefinition(DoctrineInfra\Event\UsernameListener::class)
                ->setArgument('$targetMappings', $config['username_lookup'])
                ->addTag('msgphp.domain.process_class_mapping', ['argument' => '$targetMappings', 'array_keys' => true])
            ;
        } else {
            $container->removeDefinition(DoctrineInfra\Event\UsernameListener::class);
        }

        ExtensionHelper::finalizeDoctrineOrmRepositories($container, $config['class_mapping'], Configuration::DOCTRINE_REPOSITORY_MAPPING);
    }

    private function loadConsole(array $config, LoaderInterface $loader, ContainerBuilder $container): void
    {
        $loader->load('console.php');

        $container->getDefinition(ConsoleInfra\Command\CreateUserCommand::class)
            ->setArgument('$contextFactory', ExtensionHelper::registerConsoleClassContextFactory(
                $container,
                $config['class_mapping'][Entity\User::class]
            ))
        ;

        if (isset($config['class_mapping'][Entity\Role::class])) {
            $container->getDefinition(ConsoleInfra\Command\CreateRoleCommand::class)
                ->setArgument('$contextFactory', ExtensionHelper::registerConsoleClassContextFactory(
                    $container,
                    $config['class_mapping'][Entity\Role::class]
                ))
            ;
        }

        if (isset($config['class_mapping'][Entity\UserRole::class])) {
            $container->getDefinition(ConsoleInfra\Command\AddUserRoleCommand::class)
                ->setArgument('$contextFactory', ExtensionHelper::registerConsoleClassContextFactory(
                    $container,
                    $config['class_mapping'][Entity\UserRole::class],
                    ConsoleClassContextFactory::REUSE_DEFINITION
                ))
            ;
        }

        if (isset($config['username_field'])) {
            $container->getDefinition(ConsoleInfra\Command\ChangeUserCredentialCommand::class)
                ->setArgument('$contextFactory', ExtensionHelper::registerConsoleClassContextFactory(
                    $container,
                    $config['class_mapping'][CredentialInterface::class],
                    ConsoleClassContextFactory::ALWAYS_OPTIONAL | ConsoleClassContextFactory::NO_DEFAULTS
                ))
            ;
        }

        ExtensionHelper::finalizeConsoleCommands($container, $config['commands'], Configuration::CONSOLE_COMMAND_MAPPING);
    }

    private function configureRoleProvider(array $config, ContainerBuilder $container): void
    {
        if (isset($config['class_mapping'][Entity\UserRole::class])) {
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

            $providers[] = new Reference($provider);
        }

        $container->getDefinition(Role\ChainRoleProvider::class)
            ->setArgument('$providers', new IteratorArgument($providers))
        ;
    }
}
