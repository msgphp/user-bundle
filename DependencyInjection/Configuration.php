<?php

declare(strict_types=1);

namespace MsgPhp\UserBundle\DependencyInjection;

use MsgPhp\Domain\DomainIdInterface;
use MsgPhp\Domain\Entity\Features;
use MsgPhp\Domain\Event\DomainEventHandlerInterface;
use MsgPhp\Domain\Infra\Config\{NodeBuilder, TreeBuilder};
use MsgPhp\Domain\Infra\DependencyInjection\ConfigHelper;
use MsgPhp\User\{Command, CredentialInterface, Entity, UserId, UserIdInterface};
use MsgPhp\User\Infra\{Console as ConsoleInfra, Doctrine as DoctrineInfra, Uuid as UuidInfra};
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * @author Roland Franssen <franssen.roland@gmail.com>
 *
 * @internal
 */
final class Configuration implements ConfigurationInterface
{
    public const AGGREGATE_ROOTS = [
        Entity\User::class => UserIdInterface::class,
    ];
    public const IDENTITY_MAPPING = [
        Entity\Role::class => ['name'],
        Entity\UserAttributeValue::class => ['attributeValue'],
        Entity\User::class => ['id'],
        Entity\Username::class => ['username'],
        Entity\UserRole::class => ['user', 'role'],
        Entity\UserEmail::class => ['email'],
    ];
    public const DEFAULT_ID_CLASS_MAPPING = [
        UserIdInterface::class => UserId::class,
    ];
    public const UUID_CLASS_MAPPING = [
        UserIdInterface::class => UuidInfra\UserId::class,
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
        Command\CreateUserCommand::class => [ConsoleInfra\Command\CreateUserCommand::class],
        Command\DeleteUserCommand::class => [ConsoleInfra\Command\DeleteUserCommand::class],
        Command\DeleteUserRoleCommand::class => [ConsoleInfra\Command\DeleteUserRoleCommand::class],
        Command\DisableUserCommand::class => [ConsoleInfra\Command\DisableUserCommand::class],
        Command\EnableUserCommand::class => [ConsoleInfra\Command\EnableUserCommand::class],
    ];
    private const COMMAND_MAPPING = [
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

    private static $packageDir;

    public static function getPackageDir(): string
    {
        return self::$packageDir ?? (self::$packageDir = dirname((string) (new \ReflectionClass(UserIdInterface::class))->getFileName()));
    }

    public function getConfigTreeBuilder(): TreeBuilder
    {
        /** @var NodeBuilder $children */
        $children = ($treeBuilder = new TreeBuilder())->rootArray(Extension::ALIAS)->children();

        $children
            ->classMappingNode('class_mapping')
                ->requireClasses([Entity\User::class])
                ->disallowClasses([CredentialInterface::class, Entity\Username::class])
                ->groupClasses([Entity\Role::class, Entity\UserRole::class])
                ->subClassValues()
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
                                    return Entity\Username::class === $value;
                                })
                                ->thenInvalid('Target %s is not applicable.')
                            ->end()
                            ->validate()
                                ->ifTrue(function ($value): bool {
                                    return !class_exists($value);
                                })
                                ->thenInvalid('Target %s does not exists.')
                            ->end()
                        ->end()
                        ->scalarNode('field')->isRequired()->cannotBeEmpty()->end()
                        ->scalarNode('mapped_by')->defaultValue('user')->cannotBeEmpty()->end()
                    ->end()
                ->end()
                ->validate()
                    ->always(function (array $value): array {
                        $result = [];
                        foreach ($value as $lookup) {
                            $result[$lookup['target']][$lookup['field']] = $lookup['mapped_by'];
                        }

                        return $result;
                    })
                ->end()
            ->end()
        ->end()
        ->validate()
            ->always(ConfigHelper::defaultBundleConfig(
                self::DEFAULT_ID_CLASS_MAPPING,
                array_fill_keys(ConfigHelper::UUID_TYPES, self::UUID_CLASS_MAPPING)
            ))
        ->end()
        ->validate()
            ->always(function (array $config): array {
                $userCredential = self::getUserCredential($userClass = $config['class_mapping'][Entity\User::class]);
                $config['username_field'] = $userCredential['username_field'];
                $config['class_mapping'][CredentialInterface::class] = $userCredential['class'];
                $config['commands'][Command\ChangeUserCredentialCommand::class] = isset($config['username_field']) ? is_subclass_of($userClass, DomainEventHandlerInterface::class) : false;

                if ($config['username_lookup']) {
                    if (isset($config['username_field'])) {
                        $config['username_lookup'][$userClass][$config['username_field']] = null;
                    }

                    $config['class_mapping'][Entity\Username::class] = Entity\Username::class;
                }

                ConfigHelper::resolveCommandMappingConfig(self::COMMAND_MAPPING, $config['class_mapping'], $config['commands']);

                return $config;
            })
        ->end();

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
