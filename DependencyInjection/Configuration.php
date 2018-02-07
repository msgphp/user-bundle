<?php

declare(strict_types=1);

namespace MsgPhp\UserBundle\DependencyInjection;

use MsgPhp\Domain\Entity\Features;
use MsgPhp\Domain\Infra\DependencyInjection\Bundle\ConfigHelper;
use MsgPhp\User\{Command, CredentialInterface, Entity, UserId, UserIdInterface};
use MsgPhp\User\Infra\Uuid as UuidInfra;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * @author Roland Franssen <franssen.roland@gmail.com>
 */
final class Configuration implements ConfigurationInterface
{
    public const REQUIRED_AGGREGATE_ROOTS = [
        Entity\User::class => UserIdInterface::class,
    ];
    public const OPTIONAL_AGGREGATE_ROOTS = [];
    public const AGGREGATE_ROOTS = self::REQUIRED_AGGREGATE_ROOTS + self::OPTIONAL_AGGREGATE_ROOTS;
    public const IDENTITY_MAPPING = [
        Entity\UserAttributeValue::class => ['user', 'attributeValue'],
        Entity\User::class => 'id',
        Entity\Username::class => ['user', 'username'],
        Entity\UserRole::class => ['user', 'role'],
        Entity\UserSecondaryEmail::class => ['user', 'email'],
    ];
    public const DATA_TYPE_MAPPING = [
        UserIdInterface::class => [
            UserId::class => ConfigHelper::NATIVE_DATA_TYPES,
            UuidInfra\UserId::class => ConfigHelper::UUID_DATA_TYPES,
        ],
    ];
    private const COMMAND_MAPPING = [
        Entity\User::class => [
            Features\CanBeConfirmed::class => [
                Command\ConfirmUserCommand::class,
            ],
            Features\CanBeEnabled::class => [
                Command\DisableUserCommand::class,
                Command\EnableUserCommand::class,
            ],
        ],
    ];

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder();
        $availableIds = array_values(self::AGGREGATE_ROOTS);
        $requiredEntities = array_keys(self::REQUIRED_AGGREGATE_ROOTS);

        $treeBuilder->root(Extension::ALIAS)
            ->append(
                ConfigHelper::createClassMappingNode('class_mapping', $requiredEntities, function (array $value) use ($availableIds): array {
                    return $value + array_fill_keys($availableIds, null);
                })
            )
            ->append(
                ConfigHelper::createClassMappingNode('data_type_mapping', [], function ($value) use ($availableIds): array {
                    if (!is_array($value)) {
                        $value = array_fill_keys($availableIds, $value);
                    } else {
                        $value += array_fill_keys($availableIds, null);
                    }

                    return $value;
                })->addDefaultChildrenIfNoneSet($availableIds)
            )
            ->append(
                ConfigHelper::createClassMappingNode('commands', [], null, true, 'boolean')
            )
            ->children()
                ->arrayNode('username_lookup')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('target')->isRequired()->cannotBeEmpty()->end()
                            ->scalarNode('field')->isRequired()->cannotBeEmpty()->end()
                            ->scalarNode('mapped_by')->defaultValue('user')->cannotBeEmpty()->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
            ->validate()
                ->always()
                ->then(function (array $config): array {
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

                        if (!isset($config['class_mapping'][Entity\Username::class])) {
                            $config['class_mapping'][Entity\Username::class] = Entity\Username::class;
                        }

                        if (isset($usernameLookup[$usernameClass = $config['class_mapping'][Entity\Username::class]])) {
                            throw new \LogicException(sprintf('Username lookup mapping for "%s" is not applicable.', $usernameClass));
                        }
                    }

                    $config['username_field'] = $userCredential['username_field'];
                    $config['username_lookup'] = $usernameLookup;
                    $config['commands'] += [
                        Command\CreateUserCommand::class => true,
                        Command\DeleteUserCommand::class => true,
                    ];

                    if (null !== $userCredential['class']) {
                        if (isset($config['class_mapping'][CredentialInterface::class])) {
                            throw new \LogicException(sprintf('Class mapping for "%s" cannot be overwritten.', CredentialInterface::class));
                        }

                        $config['class_mapping'][CredentialInterface::class] = $userCredential['class'];
                        $config['commands'][Command\ChangeUserCredentialCommand::class] = true;
                    }

                    ConfigHelper::resolveCommandMapping($config['class_mapping'], self::COMMAND_MAPPING, $config['commands']);

                    return $config;
                })
            ->end();

        return $treeBuilder;
    }

    private static function getUserCredential(string $userClass): array
    {
        if (null === $credential = (new \ReflectionMethod($userClass, 'getCredential'))->getReturnType()) {
            return ['class' => null, 'username_field' => null];
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
