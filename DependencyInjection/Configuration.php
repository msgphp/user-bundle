<?php

declare(strict_types=1);

namespace MsgPhp\UserBundle\DependencyInjection;

use MsgPhp\Domain\DomainId;
use MsgPhp\Domain\Infrastructure\Config\NodeBuilder;
use MsgPhp\Domain\Infrastructure\Config\TreeBuilderHelper;
use MsgPhp\Domain\Infrastructure\DependencyInjection\ConfigHelper;
use MsgPhp\Domain\Infrastructure\DependencyInjection\PackageMetadata;
use MsgPhp\Domain\Model\CanBeConfirmed;
use MsgPhp\Domain\Model\CanBeEnabled;
use MsgPhp\User\Command;
use MsgPhp\User\Credential\Anonymous;
use MsgPhp\User\Credential\Credential;
use MsgPhp\User\Credential\UsernameCredential;
use MsgPhp\User\Infrastructure\Console as ConsoleInfrastructure;
use MsgPhp\User\Infrastructure\Doctrine as DoctrineInfrastructure;
use MsgPhp\User\Infrastructure\Uuid as UuidInfrastructure;
use MsgPhp\User\Model\AbstractCredential;
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
    public const DEFAULT_ROLE = 'ROLE_USER';
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

            AbstractCredential::class => [
                Command\ChangeUserCredential::class,
            ],
            CanBeConfirmed::class => [
                Command\ConfirmUser::class,
            ],
            CanBeEnabled::class => [
                Command\DisableUser::class,
                Command\EnableUser::class,
            ],
            ResettablePassword::class => [
                Command\CancelUserPasswordRequest::class,
                Command\RequestUserPassword::class,
                Command\ResetUserPassword::class,
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

    /** @var PackageMetadata|null */
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
            ->scalarNode('credential_field')
                ->cannotBeEmpty()
                ->defaultValue('credential')
            ->end()
            ->arrayNode('username_lookup')
                ->requiresAtLeastOneElement()
                ->arrayPrototype()
                    ->children()
                        ->scalarNode('target')
                            ->isRequired()
                            ->cannotBeEmpty()
                            ->validate()
                                ->ifTrue(static function ($value): bool {
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
                    ->always(static function (array $value): array {
                        $result = [];
                        /** @var array<string|null> $lookup */
                        foreach ($value as $lookup) {
                            ['target' => $target, 'field' => $field, 'mapped_by' => $mappedBy] = $lookup;
                            if (null === $target || Username::class === $target || is_subclass_of($target, Username::class)) {
                                /** @psalm-suppress PossiblyNullOperand */
                                throw new \LogicException('Lookup target "'.$target.'" is not applicable and should be removed.');
                            }
                            if (null === $mappedBy && !is_subclass_of($target, User::class)) {
                                throw new \LogicException('Lookup for target "'.$target.'" must be a sub class of "'.User::class.'" or specify the "mapped_by" node.');
                            }

                            /** @psalm-suppress PossiblyNullArrayOffset */
                            $result[$target][$field] = $mappedBy;
                        }

                        return $result;
                    })
                ->end()
            ->end()
            ->arrayNode('role_providers')
                ->defaultValue(['default' => [self::DEFAULT_ROLE]])
                ->requiresAtLeastOneElement()
                ->cannotBeOverwritten()
                ->beforeNormalization()
                    ->always(static function ($value) {
                        if (\is_array($value)) {
                            if (!isset($value['default'])) {
                                $value['default'] = [self::DEFAULT_ROLE];
                            } elseif (false === $value['default']) {
                                $value['default'] = [];
                            } elseif (!\is_array($value['default'])) {
                                throw new \LogicException('Default role provider must be of type array or false, got "'.\gettype($value['default']).'".');
                            }
                        }

                        return $value;
                    })
                ->end()
                ->validate()
                    ->always(static function (array $value): array {
                        foreach ($value as $k => $v) {
                            if ('default' !== $k && !\is_string($v)) {
                                throw new \LogicException('Role provider must be of type string, got "'.\gettype($v).'".');
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
            ->always(static function (array $config): array {
                $userClass = $config['class_mapping'][User::class];
                $credentialClass = $config['class_mapping'][Credential::class] ?? ($config['class_mapping'][Credential::class] = self::guessUserCredential($userClass));

                $config['username_field'] = is_subclass_of($credentialClass, UsernameCredential::class) ? $config['credential_field'].'.'.$credentialClass::getUsernameField() : null;

                if ($config['username_lookup']) {
                    if (!isset($config['class_mapping'][Username::class])) {
                        throw new \LogicException('Configuring "username_lookup" requires the "'.Username::class.'" entity to be mapped under "class_mapping".');
                    }
                    if (isset($config['username_field'])) {
                        $config['username_lookup'][$userClass][$config['username_field']] = null;
                    }
                } elseif (isset($config['class_mapping'][Username::class])) {
                    throw new \LogicException('Mapping the "'.Username::class.'" entity under "class_mapping" requires "username_lookup" to be configured.');
                }

                ConfigHelper::resolveCommandMappingConfig(self::COMMAND_MAPPING, $config['class_mapping'], $config['commands']);

                return $config;
            })
        ->end()
        ;

        return $treeBuilder;
    }

    private static function guessUserCredential(string $class): string
    {
        if (User::class === (new \ReflectionMethod($class, 'getCredential'))->getDeclaringClass()->getName()) {
            return Anonymous::class;
        }

        $uses = class_uses($class);

        foreach (self::getPackageMetadata()->findPaths('Model') as $model) {
            $credential = self::PACKAGE_NS.'Credential\\'.($model = substr(basename($model, '.php'), 0, -10));
            if (isset($uses[self::PACKAGE_NS.'Model\\'.$model.'Credential']) && is_subclass_of($credential, Credential::class)) {
                return $credential;
            }
        }

        return Anonymous::class;
    }
}
