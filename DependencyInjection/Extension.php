<?php

declare(strict_types=1);

namespace MsgPhp\UserBundle\DependencyInjection;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use MsgPhp\Domain\Infra\DependencyInjection\Bundle\{ConfigHelper, ContainerHelper};
use MsgPhp\EavBundle\MsgPhpEavBundle;
use MsgPhp\User\{CredentialInterface, UserIdInterface};
use MsgPhp\User\Entity\{User, UserAttributeValue, UserRole, UserSecondaryEmail};
use MsgPhp\User\Infra\Doctrine\EntityFieldsMapping;
use MsgPhp\User\Infra\Doctrine\Repository\{UserAttributeValueRepository, UserRepository, UserRoleRepository, UserSecondaryEmailRepository};
use MsgPhp\User\Infra\Doctrine\Type\UserIdType;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension as BaseExtension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

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
        ContainerHelper::configureDoctrineOrmMapping($container, self::getDoctrineMappingFiles($container), [EntityFieldsMapping::class]);

        $bundles = ContainerHelper::getBundles($container);

        // persistence infra
        if (isset($bundles[DoctrineBundle::class])) {
            $this->prepareDoctrineBundle($config, $loader, $container);
        }

        // framework infra
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
        ContainerHelper::configureDoctrineOrmTargetEntities($container, $config['class_mapping']);
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
                continue;
            }

            ($definition = $container->getDefinition($repository))
                ->setArgument('$class', $class);

            if (UserRepository::class === $repository && null !== ($credentialType = (new \ReflectionMethod($class, 'getCredential'))->getReturnType())) {
                if ($credentialType->isBuiltin() || !is_subclass_of($credentialClass = $credentialType->getName(), CredentialInterface::class)) {
                    throw new \LogicException(sprintf('Method "%s::getCredential()" must return a sub class of "%s", got "%s".', $class, CredentialInterface::class, $credentialType->getName()));
                }
                if ($credentialType->allowsNull()) {
                    throw new \LogicException(sprintf('Method "%s::getCredential()" cannot be null-able.', $class));
                }

                $definition->setArgument('$fieldMapping', ['username' => 'credential.'.$credentialClass::getUsernameField()]);
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
