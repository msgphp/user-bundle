<?php

declare(strict_types=1);

namespace MsgPhp\UserBundle\DependencyInjection;

use Doctrine\ORM\Version as DoctrineOrmVersion;
use MsgPhp\Domain\Factory\EntityAwareFactoryInterface;
use MsgPhp\Domain\Infra\Console as BaseConsoleInfra;
use MsgPhp\Domain\Infra\DependencyInjection\ContainerHelper;
use MsgPhp\Domain\Message\MessageReceivingInterface;
use MsgPhp\EavBundle\MsgPhpEavBundle;
use MsgPhp\User\{CredentialInterface, Entity, Repository, UserIdInterface};
use MsgPhp\User\Infra\{Console as ConsoleInfra, Doctrine as DoctrineInfra, Security as SecurityInfra};
use MsgPhp\UserBundle\Twig;
use Symfony\Bundle\TwigBundle\TwigBundle;
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
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Validator\Validation;
use Twig\Environment as TwigEnvironment;

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
        $loader = new PhpFileLoader($container, new FileLocator(dirname(__DIR__).'/Resources/config'));
        $config = $this->processConfiguration($this->getConfiguration($configs, $container), $configs);

        // default infra
        $loader->load('services.php');

        ContainerHelper::configureIdentityMapping($container, $config['class_mapping'], Configuration::IDENTITY_MAPPING);
        ContainerHelper::configureEntityFactory($container, $config['class_mapping'], Configuration::AGGREGATE_ROOTS);

        // message infra
        $loader->load('message.php');

        ContainerHelper::configureCommandMessages($container, $config['class_mapping'], $config['commands']);
        ContainerHelper::configureEventMessages($container, $config['class_mapping'], array_map(function (string $file): string {
            return 'MsgPhp\\User\\Event\\'.basename($file, '.php');
        }, glob(Configuration::getPackageDir().'/Event/*Event.php')));

        // persistence infra
        if (class_exists(DoctrineOrmVersion::class)) {
            $this->loadDoctrineOrm($config, $loader, $container);
        }

        // framework infra
        if (class_exists(Security::class)) {
            $loader->load('security.php');
        }

        if (class_exists(Form::class)) {
            $loader->load('form.php');
        }

        if (class_exists(Validation::class)) {
            $loader->load('validator.php');
        }

        if (class_exists(TwigEnvironment::class)) {
            $loader->load('twig.php');
        }

        if (class_exists(ConsoleEvents::class)) {
            $loader->load('console.php');

            $container->getDefinition(ConsoleInfra\Command\CreateUserCommand::class)
                ->setArgument('$contextFactory', ContainerHelper::registerConsoleClassContextFactory(
                    $container,
                    $config['class_mapping'][Entity\User::class]
                ));

            if (isset($config['class_mapping'][Entity\UserRole::class])) {
                $container->getDefinition(ConsoleInfra\Command\AddUserRoleCommand::class)
                    ->setArgument('$contextFactory', ContainerHelper::registerConsoleClassContextFactory(
                        $container,
                        $config['class_mapping'][Entity\UserRole::class],
                        BaseConsoleInfra\Context\ClassContextFactory::REUSE_DEFINITION
                    ));
            } else {
                $container->removeDefinition(ConsoleInfra\Command\AddUserRoleCommand::class);
                $container->removeDefinition(ConsoleInfra\Command\DeleteUserRoleCommand::class);
            }

            if (isset($config['class_mapping'][CredentialInterface::class])) {
                $container->getDefinition(ConsoleInfra\Command\ChangeUserCredentialCommand::class)
                    ->setArgument('$contextFactory', ContainerHelper::registerConsoleClassContextFactory(
                        $container,
                        $config['class_mapping'][CredentialInterface::class],
                        BaseConsoleInfra\Context\ClassContextFactory::ALWAYS_OPTIONAL | BaseConsoleInfra\Context\ClassContextFactory::NO_DEFAULTS
                    ));
            } else {
                $container->removeDefinition(ConsoleInfra\Command\ChangeUserCredentialCommand::class);
            }

            foreach (glob(Configuration::getPackageDir().'/Infra/Console/Command/*Command.php') as $file) {
                if (!$container->hasDefinition($id = 'MsgPhp\\User\\Infra\\Console\\Command\\'.basename($file, '.php'))) {
                    continue;
                }

                $definition = $container->getDefinition($id);
                if (is_subclass_of($definition->getClass() ?? $id, MessageReceivingInterface::class)) {
                    $definition->addTag('msgphp.domain.message_aware');
                }
            }
        }
    }

    public function prepend(ContainerBuilder $container): void
    {
        $config = $this->processConfiguration($this->getConfiguration($configs = $container->getExtensionConfig($this->getAlias()), $container), $configs);

        ContainerHelper::configureDoctrineDbalTypes($container, $config['class_mapping'], $config['id_type_mapping'], [
            UserIdInterface::class => DoctrineInfra\Type\UserIdType::class,
        ]);
        ContainerHelper::configureDoctrineOrmTargetEntities($container, $config['class_mapping']);

        if (ContainerHelper::hasBundle($container, TwigBundle::class)) {
            $container->prependExtensionConfig('twig', [
                'globals' => [
                    'msgphp_user' => '@'.Twig\GlobalVariables::class,
                ],
            ]);
        }
    }

    public function process(ContainerBuilder $container): void
    {
        if ($container->hasDefinition('data_collector.security')) {
            $container->getDefinition('data_collector.security')
                ->setClass(SecurityInfra\DataCollector::class)
                ->setArgument('$repository', new Reference(Repository\UserRepositoryInterface::class, ContainerBuilder::NULL_ON_INVALID_REFERENCE))
                ->setArgument('$factory', new Reference(EntityAwareFactoryInterface::class));
        }
    }

    private function loadDoctrineOrm(array $config, LoaderInterface $loader, ContainerBuilder $container): void
    {
        $loader->load('doctrine.php');

        if (isset($config['username_field'])) {
            $container->getDefinition(DoctrineInfra\Repository\UserRepository::class)
                ->setArgument('$usernameField', $config['username_field']);
        }

        if ($config['username_lookup']) {
            $container->getDefinition(DoctrineInfra\Event\UsernameListener::class)
                ->setArgument('$mapping', $config['username_lookup'])
                ->addTag('msgphp.domain.process_class_mapping', ['argument' => '$mapping', 'array_keys' => true]);

            $container->getDefinition(DoctrineInfra\Repository\UsernameRepository::class)
                ->setArgument('$targetMapping', $config['username_lookup'])
                ->addTag('msgphp.domain.process_class_mapping', ['argument' => '$targetMapping', 'array_keys' => true]);
        } else {
            $container->removeDefinition(DoctrineInfra\Event\UsernameListener::class);
        }

        ContainerHelper::configureDoctrineOrmMapping($container, self::getDoctrineMappingFiles($config, $container), [DoctrineInfra\EntityFieldsMapping::class]);
        ContainerHelper::configureDoctrineOrmRepositories($container, $config['class_mapping'], [
            DoctrineInfra\Repository\RoleRepository::class => Entity\Role::class,
            DoctrineInfra\Repository\UserRepository::class => Entity\User::class,
            DoctrineInfra\Repository\UsernameRepository::class => Entity\Username::class,
            DoctrineInfra\Repository\UserAttributeValueRepository::class => Entity\UserAttributeValue::class,
            DoctrineInfra\Repository\UserRoleRepository::class => Entity\UserRole::class,
            DoctrineInfra\Repository\UserEmailRepository::class => Entity\UserEmail::class,
        ]);
    }

    private static function getDoctrineMappingFiles(array $config, ContainerBuilder $container): array
    {
        $baseDir = Configuration::getPackageDir().'/Infra/Doctrine/Resources/dist-mapping';
        $files = array_flip(glob($baseDir.'/*.orm.xml'));

        if (!isset($config['class_mapping'][Entity\Role::class])) {
            unset($files[$baseDir.'/User.Entity.Role.orm.xml'], $files[$baseDir.'/User.Entity.UserRole.orm.xml']);
        }

        if (!isset($config['class_mapping'][Entity\UserEmail::class])) {
            unset($files[$baseDir.'/User.Entity.UserEmail.orm.xml']);
        }

        if (!isset($config['class_mapping'][Entity\Username::class])) {
            unset($files[$baseDir.'/User.Entity.Username.orm.xml']);
        }

        if (!ContainerHelper::hasBundle($container, MsgPhpEavBundle::class)) {
            unset($files[$baseDir.'/User.Entity.UserAttributeValue.orm.xml']);
        }

        return array_keys($files);
    }
}
