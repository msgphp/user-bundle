<?php

declare(strict_types=1);

namespace MsgPhp\UserBundle\DependencyInjection;

use MsgPhp\Domain\DomainId;
use MsgPhp\Domain\Event\DomainEventHandler;
use MsgPhp\Domain\Infrastructure\Config\NodeBuilder;
use MsgPhp\Domain\Infrastructure\Config\TreeBuilderHelper;
use MsgPhp\Domain\Infrastructure\DependencyInjection\ConfigHelper;
use MsgPhp\Domain\Infrastructure\DependencyInjection\PackageMetadata;
use MsgPhp\Domain\Model\CanBeConfirmed;
use MsgPhp\Domain\Model\CanBeEnabled;
use MsgPhp\User\Command;
use MsgPhp\User\Credential\Anonymous;
use MsgPhp\User\Credential\Credential;
use MsgPhp\User\Infrastructure\Console as ConsoleInfrastructure;
use MsgPhp\User\Infrastructure\Doctrine as DoctrineInfrastructure;
use MsgPhp\User\Infrastructure\Uuid as UuidInfrastructure;
use MsgPhp\User\Model\ResettablePassword;
use MsgPhp\User\Role;
use MsgPhp\User\ScalarUserId;
use MsgPhp\User\User;
use MsgPhp\User\UserAttributeValue;
use MsgPhp\User\UserEmail;
use MsgPhp\User\UserId;
use MsgPhp\User\Username;
use MsgPhp\User\UserRole;
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
    public const DOCTRINE_TYPE_MAPPING = [
        UserId::class => DoctrineInfrastructure\Type\UserIdType::class,
    ];
    public const DOCTRINE_REPOSITORY_MAPPING = [
        Role::class => DoctrineInfrastructure\Repository\RoleRepository::class,
        User::class => DoctrineInfrastructure\Repository\UserRepository::class,
        Username::class => DoctrineInfrastructure\Repository\UsernameRepository::class,
        UserAttributeValue::class => DoctrineInfrastructure\Repository\UserAttributeValueRepository::class,
        UserRole::class => DoctrineInfrastructure\Repository\UserRoleRepository::class,
        UserEmail::class => DoctrineInfrastructure\Repository\UserEmailRepository::class,
    ];
    public const CONSOLE_COMMAND_MAPPING = [
        Command\AddUserRole::class => [ConsoleInfrastructure\Command\AddUserRoleCommand::class],
        Command\ChangeUserCredential::class => [ConsoleInfrastructure\Command\ChangeUserCredentialCommand::class],
        Command\ConfirmUser::class => [ConsoleInfrastructure\Command\ConfirmUserCommand::class],
        Command\CreateRole::class => [ConsoleInfrastructure\Command\CreateRoleCommand::class],
        Command\CreateUser::class => [ConsoleInfrastructure\Command\CreateUserCommand::class],
        Command\DeleteRole::class => [ConsoleInfrastructure\Command\DeleteRoleCommand::class],
        Command\DeleteUser::class => [ConsoleInfrastructure\Command\DeleteUserCommand::class],
        Command\DeleteUserRole::class => [ConsoleInfrastructure\Command\DeleteUserRoleCommand::class],
        Command\DisableUser::class => [ConsoleInfrastructure\Command\DisableUserCommand::class],
        Command\EnableUser::class => [ConsoleInfrastructure\Command\EnableUserCommand::class],
    ];
    private const ID_TYPE_MAPPING = [
        UserId::class => [
            'scalar' => ScalarUserId::class,
            'uuid' => UuidInfrastructure\UserUuid::class,
        ],
    ];
    private const COMMAND_MAPPING = [
        Role::class => [
            Command\CreateRole::class,
            Command\DeleteRole::class,
        ],
        User::class => [
            Command\CreateUser::class,
            Command\DeleteUser::class,

            CanBeConfirmed::class => [
                Command\ConfirmUser::class,
            ],
            CanBeEnabled::class => [
                Command\DisableUser::class,
                Command\EnableUser::class,
            ],
            ResettablePassword::class => [
                Command\RequestUserPassword::class,
            ],
        ],
        UserAttributeValue::class => [
            Command\AddUserAttributeValue::class,
            Command\ChangeUserAttributeValue::class,
            Command\DeleteUserAttributeValue::class,
        ],
        UserEmail::class => [
            Command\AddUserEmail::class,
            Command\DeleteUserEmail::class,

            CanBeConfirmed::class => [
                Command\ConfirmUserEmail::class,
            ],
        ],
        UserRole::class => [
            Command\AddUserRole::class,
            Command\DeleteUserRole::class,
        ],
    ];
    private const DEFAULT_ROLE = 'ROLE_USER';

    /**
     * @var PackageMetadata|null
     */
    private static $packageMetadata;

    public static function getPackageMetadata(): PackageMetadata
    {
        if (null !== self::$packageMetadata) {
            return self::$packageMetadata;
        }

        $dirs = [
            \dirname((string) (new \ReflectionClass(UserId::class))->getFileName()),
        ];

        if (class_exists(UserAttributeValue::class)) {
            $dirs[] = \dirname((string) (new \ReflectionClass(UserAttributeValue::class))->getFileName());
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
                ->requireClasses([User::class])
                ->disallowClasses([Credential::class])
                ->groupClasses([Role::class, UserRole::class])
                ->subClassValues()
                ->hint(UserAttributeValue::class, 'Try running "composer require msgphp/user-eav msgphp/eav-bundle".')
            ->end()
            ->classMappingNode('id_type_mapping')
                ->subClassKeys([DomainId::class])
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
                            if (Username::class === $target || is_subclass_of($target, Username::class)) {
                                throw new \LogicException(sprintf('Lookup target "%s" is not applicable and should be removed.', (string) $target));
                            }
                            if (null === $mappedBy && !is_subclass_of($target, User::class)) {
                                throw new \LogicException(sprintf('Lookup for target "%s" must be a sub class of "%s" or specify the "mapped_by" node.', $target, User::class));
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
            ->always(ConfigHelper::defaultBundleConfig(self::ID_TYPE_MAPPING))
        ->end()
        ->validate()
            ->always(function (array $config): array {
                $userCredential = self::getUserCredential($userClass = $config['class_mapping'][User::class]);
                $config['username_field'] = $userCredential['username_field'];
                $config['class_mapping'][Credential::class] = $userCredential['class'];

                if (!isset($config['commands'][Command\ChangeUserCredential::class])) {
                    $config['commands'][Command\ChangeUserCredential::class] = isset($config['username_field']) ? is_subclass_of($userClass, DomainEventHandler::class) : false;
                }

                if ($config['username_lookup']) {
                    if (!isset($config['class_mapping'][Username::class])) {
                        throw new \LogicException(sprintf('Configuring "username_lookup" requires the "%s" entity to be mapped under "class_mapping".', Username::class));
                    }
                    if (isset($config['username_field'])) {
                        $config['username_lookup'][$userClass][$config['username_field']] = null;
                    }
                } elseif (isset($config['class_mapping'][Username::class])) {
                    throw new \LogicException(sprintf('Mapping the "%s" entity under "class_mapping" requires "username_lookup" to be configured.', Username::class));
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

        if (User::class === $reflection->getDeclaringClass()->getName()) {
            return ['class' => Anonymous::class, 'username_field' => null];
        }

        if (null === $type = $reflection->getReturnType()) {
            throw new \LogicException(sprintf('Method "%s::getCredential()" must have a return type set.', $userClass));
        }

        if (Anonymous::class === $class = $type->getName()) {
            return ['class' => $class, 'username_field' => null];
        }

        if ($type->isBuiltin() || !is_subclass_of($class, Credential::class)) {
            throw new \LogicException(sprintf('Method "%s::getCredential()" must return a sub class of "%s", got "%s".', $userClass, Credential::class, $class));
        }

        if ($type->allowsNull()) {
            throw new \LogicException(sprintf('Method "%s::getCredential()" cannot be null-able.', $userClass));
        }

        return ['class' => $class, 'username_field' => 'credential.'.$class::getUsernameField()];
    }
}
