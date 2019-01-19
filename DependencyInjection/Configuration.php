<?php

declare(strict_types=1);

namespace MsgPhp\UserBundle\DependencyInjection;

use MsgPhp\Domain\DomainIdInterface;
use MsgPhp\Domain\Entity\Features;
use MsgPhp\Domain\Event\DomainEventHandlerInterface;
use MsgPhp\Domain\Infra\Config\{NodeBuilder, TreeBuilderHelper};
use MsgPhp\Domain\Infra\DependencyInjection\{ConfigHelper, PackageMetadata};
use MsgPhp\User\{Command, CredentialInterface, Entity, UserId, UserIdInterface};
use MsgPhp\User\Infra\{Console as ConsoleInfra, Doctrine as DoctrineInfra, Uuid as UuidInfra};
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * @author Roland Franssen <franssen.roland@gmail.com>
 *
 * @internal
 */
final class Configuration implements ConfigurationInterface
{
    public const PACKAGE_NS = 'MsgPhp\\User\\';
    public const IDENTITY_MAPPING = [
        Entity\Role::class => ['name'],
        Entity\UserAttributeValue::class => ['attributeValue'],
        Entity\User::class => ['id'],
        Entity\Username::class => ['username'],
        Entity\UserRole::class => ['user', 'role'],
        Entity\UserEmail::class => ['email'],
    ];
    public const DOCTRINE_TYPE_MAPPING = [
        UserIdInterface::class => DoctrineInfra\Type\UserIdType::class,
    ];
    public const DOCTRINE_REPOSITORY_MAPPING = [
        Entity\Role::class => DoctrineInfra\Repository\RoleRepository::class,
        Entity\User::class => DoctrineInfra\Repository\UserRepository::class,
        Entity\Username::class => DoctrineInfra\Repository\UsernameRepository::class,
        Entity\UserAttributeValue::class => DoctrineInfra\Repository\UserAttributeValueRepository::class,
        Entity\UserRole::class => DoctrineInfra\Repository\UserRoleRepository::class,
        Entity\UserEmail::class => DoctrineInfra\Repository\UserEmailRepository::class,
    ];
    public const CONSOLE_COMMAND_MAPPING = [
        Command\AddUserRoleCommand::class => [ConsoleInfra\Command\AddUserRoleCommand::class],
        Command\ChangeUserCredentialCommand::class => [ConsoleInfra\Command\ChangeUserCredentialCommand::class],
        Command\ConfirmUserCommand::class => [ConsoleInfra\Command\ConfirmUserCommand::class],
        Command\CreateRoleCommand::class => [ConsoleInfra\Command\CreateRoleCommand::class],
        Command\CreateUserCommand::class => [ConsoleInfra\Command\CreateUserCommand::class],
        Command\DeleteRoleCommand::class => [ConsoleInfra\Command\DeleteRoleCommand::class],
        Command\DeleteUserCommand::class => [ConsoleInfra\Command\DeleteUserCommand::class],
        Command\DeleteUserRoleCommand::class => [ConsoleInfra\Command\DeleteUserRoleCommand::class],
        Command\DisableUserCommand::class => [ConsoleInfra\Command\DisableUserCommand::class],
        Command\EnableUserCommand::class => [ConsoleInfra\Command\EnableUserCommand::class],
    ];
    private const DEFAULT_ID_MAPPING = [
        UserIdInterface::class => UserId::class,
    ];
    private const UUID_MAPPING = [
        UserIdInterface::class => UuidInfra\UserId::class,
    ];
    private const COMMAND_MAPPING = [
        Entity\Role::class => [
            Command\CreateRoleCommand::class,
            Command\DeleteRoleCommand::class,
        ],
        Entity\User::class => [
            Command\CreateUserCommand::class,
            Command\DeleteUserCommand::class,

            Features\CanBeConfirmed::class => [
                Command\ConfirmUserCommand::class,
            ],
            Features\CanBeEnabled::class => [
                Command\DisableUserCommand::class,
                Command\EnableUserCommand::class,
            ],
            Entity\Features\ResettablePassword::class => [
                Command\RequestUserPasswordCommand::class,
            ],
        ],
        Entity\UserAttributeValue::class => [
            Command\AddUserAttributeValueCommand::class,
            Command\ChangeUserAttributeValueCommand::class,
            Command\DeleteUserAttributeValueCommand::class,
        ],
        Entity\UserEmail::class => [
            Command\AddUserEmailCommand::class,
            Command\DeleteUserEmailCommand::class,

            Features\CanBeConfirmed::class => [
                Command\ConfirmUserEmailCommand::class,
            ],
        ],
        Entity\UserRole::class => [
            Command\AddUserRoleCommand::class,
            Command\DeleteUserRoleCommand::class,
        ],
    ];
    private const DEFAULT_ROLE = 'ROLE_USER';

    private static $packageMetadata;

    public static function getPackageMetadata(): PackageMetadata
    {
        if (null !== self::$packageMetadata) {
            return self::$packageMetadata;
        }

        $dirs = [
            \dirname((string) (new \ReflectionClass(UserIdInterface::class))->getFileName()),
        ];

        if (class_exists(Entity\UserAttributeValue::class)) {
            $dirs[] = \dirname((string) (new \ReflectionClass(Entity\UserAttributeValue::class))->getFileName(), 2);
        }

        return self::$packageMetadata = new PackageMetadata(self::PACKAGE_NS, $dirs);
    }

    public function getConfigTreeBuilder(): TreeBuilder
    {
        /** @var NodeBuilder $children */
        $children = TreeBuilderHelper::root(Extension::ALIAS, $treeBuilder)->children();
        /** @psalm-suppress PossiblyNullReference */
        $children
            ->classMappingNode('class_mapping')
                ->requireClasses([Entity\User::class])
                ->disallowClasses([CredentialInterface::class])
                ->groupClasses([Entity\Role::class, Entity\UserRole::class])
                ->subClassValues()
                ->hint(Entity\UserAttributeValue::class, 'Try running "composer require msgphp/user-eav msgphp/eav-bundle".')
            ->end()
            ->classMappingNode('id_type_mapping')
                ->subClassKeys([DomainIdInterface::class])
            ->end()
            ->classMappingNode('commands')
                ->typeOfValues('boolean')
            ->end()
            ->scalarNode('default_id_type')
                ->defaultValue(ConfigHelper::DEFAULT_ID_TYPE)
                ->cannotBeEmpty()
            ->end()
            ->arrayNode('username_lookup')
                ->requiresAtLeastOneElement()
                ->arrayPrototype()
                    ->children()
                        ->scalarNode('target')
                            ->isRequired()
                            ->cannotBeEmpty()
                            ->validate()
                                ->ifTrue(function ($value): bool {
                                    return !class_exists($value);
                                })
                                ->thenInvalid('Target class %s does not exists.')
                            ->end()
                        ->end()
                        ->scalarNode('field')->isRequired()->cannotBeEmpty()->end()
                        ->scalarNode('mapped_by')->defaultNull()->end()
                    ->end()
                ->end()
                ->validate()
                    ->always(function (array $value): array {
                        $result = [];
                        foreach ($value as $lookup) {
                            ['target' => $target, 'field' => $field, 'mapped_by' => $mappedBy] = $lookup;
                            if (Entity\Username::class === $target || is_subclass_of($target, Entity\Username::class)) {
                                throw new \LogicException(sprintf('Lookup target "%s" is not applicable.', (string) $target));
                            }
                            if (null === $mappedBy && !is_subclass_of($target, Entity\User::class)) {
                                throw new \LogicException(sprintf('Lookup for target "%s" must be a sub class of "%s" or specify the "mapped_by" node.', $target, Entity\User::class));
                            }

                            $result[$target][$field] = $mappedBy;
                        }

                        return $result;
                    })
                ->end()
            ->end()
            ->arrayNode('role_providers')
                ->defaultValue(['default' => [self::DEFAULT_ROLE]])
                ->requiresAtLeastOneElement()
                ->beforeNormalization()
                    ->always(function ($value) {
                        if (\is_array($value)) {
                            if (!isset($value['default'])) {
                                $value['default'] = [self::DEFAULT_ROLE];
                            } elseif (false === $value['default']) {
                                $value['default'] = [];
                            } elseif (!\is_array($value['default'])) {
                                throw new \LogicException(sprintf('Default role provider must be of type array or false, got "%s".', \gettype($value['default'])));
                            }
                        }

                        return $value;
                    })
                ->end()
                ->validate()
                    ->always(function (array $value): array {
                        foreach ($value as $k => $v) {
                            if ('default' !== $k && !\is_string($v)) {
                                throw new \LogicException(sprintf('Role provider must be of type string, got "%s".', \gettype($v)));
                            }
                        }

                        return $value;
                    })
                ->end()
                ->variablePrototype()->end()
            ->end()
            ->arrayNode('doctrine')
                ->addDefaultsIfNotSet()
                ->children()
                    ->booleanNode('auto_sync_username')->defaultTrue()->end()
                ->end()
            ->end()
        ->end()
        ->validate()
            ->always(ConfigHelper::defaultBundleConfig(
                self::DEFAULT_ID_MAPPING,
                array_fill_keys(ConfigHelper::UUID_TYPES, self::UUID_MAPPING)
            ))
        ->end()
        ->validate()
            ->always(function (array $config): array {
                $userCredential = self::getUserCredential($userClass = $config['class_mapping'][Entity\User::class]);
                $config['username_field'] = $userCredential['username_field'];
                $config['class_mapping'][CredentialInterface::class] = $userCredential['class'];
                $config['commands'][Command\ChangeUserCredentialCommand::class] = isset($config['username_field']) ? is_subclass_of($userClass, DomainEventHandlerInterface::class) : false;

                if ($config['username_lookup']) {
                    if (!isset($config['class_mapping'][Entity\Username::class])) {
                        throw new \LogicException(sprintf('Configuring "username_lookup" requires the "%s" entity to be mapped under "class_mapping".', Entity\Username::class));
                    }
                    if (isset($config['username_field'])) {
                        $config['username_lookup'][$userClass][$config['username_field']] = null;
                    }
                }

                ConfigHelper::resolveCommandMappingConfig(self::COMMAND_MAPPING, $config['class_mapping'], $config['commands']);

                return $config;
            })
        ->end()
        ;

        return $treeBuilder;
    }

    private static function getUserCredential(string $userClass): array
    {
        $reflection = new \ReflectionMethod($userClass, 'getCredential');

        if (Entity\User::class === $reflection->getDeclaringClass()->getName()) {
            return ['class' => Entity\Credential\Anonymous::class, 'username_field' => null];
        }

        $type = $reflection->getReturnType();

        if (null === $type) {
            throw new \LogicException(sprintf('Method "%s::getCredential()" must have a return type set.', $userClass));
        }

        if (Entity\Credential\Anonymous::class === $class = $type->getName()) {
            return ['class' => $class, 'username_field' => null];
        }

        if ($type->isBuiltin() || !is_subclass_of($class, CredentialInterface::class)) {
            throw new \LogicException(sprintf('Method "%s::getCredential()" must return a sub class of "%s", got "%s".', $userClass, CredentialInterface::class, $class));
        }

        if ($type->allowsNull()) {
            throw new \LogicException(sprintf('Method "%s::getCredential()" cannot be null-able.', $userClass));
        }

        return ['class' => $class, 'username_field' => 'credential.'.$class::getUsernameField()];
    }
}
