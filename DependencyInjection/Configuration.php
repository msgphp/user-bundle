<?php

declare(strict_types=1);

namespace MsgPhp\UserBundle\DependencyInjection;

use MsgPhp\Domain\DomainIdInterface;
use MsgPhp\Domain\Entity\Features;
use MsgPhp\Domain\Infra\Config\{NodeBuilder, TreeBuilder};
use MsgPhp\Domain\Infra\DependencyInjection\ConfigHelper;
use MsgPhp\User\{Command, CredentialInterface, Entity, UserId, UserIdInterface};
use MsgPhp\User\Infra\Uuid as UuidInfra;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * @author Roland Franssen <franssen.roland@gmail.com>
 *
 * @internal
 */
final class Configuration implements ConfigurationInterface
{
    public const REQUIRED_AGGREGATE_ROOTS = [
        Entity\User::class => UserIdInterface::class,
    ];
    public const OPTIONAL_AGGREGATE_ROOTS = [];
    public const AGGREGATE_ROOTS = self::REQUIRED_AGGREGATE_ROOTS + self::OPTIONAL_AGGREGATE_ROOTS;
    public const IDENTITY_MAPPING = [
        Entity\Role::class => ['name'],
        Entity\UserAttributeValue::class => ['user', 'attributeValue'],
        Entity\User::class => ['id'],
        Entity\Username::class => ['user', 'username'],
        Entity\UserRole::class => ['user', 'role'],
        Entity\UserEmail::class => ['user', 'email'],
    ];
    public const DEFAULT_ID_CLASS_MAPPING = [
        UserIdInterface::class => UserId::class,
    ];
    public const UUID_CLASS_MAPPING = [
        UserIdInterface::class => UuidInfra\UserId::class,
    ];
    private const COMMAND_MAPPING = [
        Entity\User::class => [
            Command\CreateUserCommand::class => true,
            Command\DeleteUserCommand::class => true,

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
        Entity\UserEmail::class => [
            Command\AddUserEmailCommand::class => true,
            Command\DeleteUserEmailCommand::class => true,

            Features\CanBeConfirmed::class => [
                Command\ConfirmUserEmailCommand::class,
            ],
        ],
    ];

    public function getConfigTreeBuilder(): TreeBuilder
    {
        /** @var NodeBuilder $children */
        $children = ($treeBuilder = new TreeBuilder())->rootArray(Extension::ALIAS)->children();

        $children
            ->classMappingNode('class_mapping')
                ->requireClasses(array_keys(self::REQUIRED_AGGREGATE_ROOTS))
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
                        ->end()
                        ->scalarNode('field')->isRequired()->cannotBeEmpty()->end()
                        ->scalarNode('mapped_by')->defaultValue('user')->cannotBeEmpty()->end()
                    ->end()
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
                $usernameLookup = [];
                foreach ($config['username_lookup'] as &$value) {
                    if (isset($config['class_mapping'][$value['target']])) {
                        $value['target'] = $config['class_mapping'][$value['target']];
                    }

                    if (isset($usernameLookup[$value['target']]) && in_array($value, $usernameLookup[$value['target']], true)) {
                        throw new \LogicException(sprintf('Duplicate username lookup mapping for "%s".', $value['target']));
                    }

                    $usernameLookup[$value['target']][] = $value;
                }
                unset($value);

                $userCredential = self::getUserCredential($userClass = $config['class_mapping'][Entity\User::class]);

                if ($usernameLookup) {
                    if (isset($usernameLookup[$userClass])) {
                        throw new \LogicException(sprintf('Username lookup mapping for "%s" cannot be overwritten.', $userClass));
                    }

                    if (null !== $userCredential['username_field']) {
                        $usernameLookup[$userClass][] = ['target' => $userClass, 'field' => $userCredential['username_field']];
                    }
                }

                $config['class_mapping'][CredentialInterface::class] = $userCredential['class'];
                $config['username_field'] = $userCredential['username_field'];
                $config['username_lookup'] = $usernameLookup;

                if (null !== $userCredential['username_field'] && !isset($config['commands'][Command\ChangeUserCredentialCommand::class])) {
                    $config['commands'][Command\ChangeUserCredentialCommand::class] = true;
                }

                ConfigHelper::resolveCommandMappingConfig(self::COMMAND_MAPPING, $config['class_mapping'], $config['commands']);

                return $config;
            })
        ->end();

        return $treeBuilder;
    }

    private static function getUserCredential(string $userClass): array
    {
        if (null === $credential = (new \ReflectionMethod($userClass, 'getCredential'))->getReturnType()) {
            return ['class' => Entity\Credential\Anonymous::class, 'username_field' => null];
        }

        if ($credential->isBuiltin() || !is_subclass_of($class = $credential->getName(), CredentialInterface::class)) {
            throw new \LogicException(sprintf('Method "%s::getCredential()" must return a sub class of "%s", got "%s".', $userClass, CredentialInterface::class, $credential->getName()));
        }

        if ($credential->allowsNull()) {
            throw new \LogicException(sprintf('Method "%s::getCredential()" cannot be null-able.', $userClass));
        }

        return ['class' => $class, 'username_field' => 'credential.'.$class::getUsernameField()];
    }
}
