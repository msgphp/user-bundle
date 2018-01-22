<?php

declare(strict_types=1);

namespace MsgPhp\UserBundle\DependencyInjection;

use MsgPhp\Domain\Infra\DependencyInjection\Bundle\ConfigHelper;
use MsgPhp\User\Entity\{Credential, User, UserAttributeValue, UserRole, UserSecondaryEmail};
use MsgPhp\User\Infra\Uuid;
use MsgPhp\User\{CredentialInterface, UserId, UserIdInterface};
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * @author Roland Franssen <franssen.roland@gmail.com>
 */
final class Configuration implements ConfigurationInterface
{
    public const IDENTITY_MAP = [
        UserAttributeValue::class => ['user', 'attributeValue'],
        User::class => 'id',
        UserRole::class => ['user', 'role'],
        UserSecondaryEmail::class => ['user', 'email'],
    ];
    public const DATA_TYPE_MAP = [
        UserIdInterface::class => [
            UserId::class => ConfigHelper::NATIVE_DATA_TYPES,
            Uuid\UserId::class => ConfigHelper::UUID_DATA_TYPES,
        ],
    ];
    public const REQUIRED_AGGREGATE_ROOTS = [
        User::class => UserIdInterface::class,
    ];
    public const OPTIONAL_AGGREGATE_ROOTS = [];
    public const AGGREGATE_ROOTS = self::REQUIRED_AGGREGATE_ROOTS + self::OPTIONAL_AGGREGATE_ROOTS;

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder();
        $requiredEntities = array_keys(self::REQUIRED_AGGREGATE_ROOTS);
        $availableIds = array_values(self::AGGREGATE_ROOTS);

        $treeBuilder->root(Extension::ALIAS)
            ->append(
                ConfigHelper::createClassMappingNode('class_mapping', $requiredEntities, function (array $value) use ($availableIds): array {
                    $value = $value + array_fill_keys($availableIds, null);

                    if (!isset($value[CredentialInterface::class])) {
                        $value[CredentialInterface::class] = Credential\Anonymous::class;
                    }

                    return $value;
                })
            )
            ->append(
                ConfigHelper::createClassMappingNode('data_type_mapping', [], function ($value) use ($availableIds) {
                    if (!is_array($value)) {
                        $value = array_fill_keys($availableIds, $value);
                    } else {
                        $value += array_fill_keys($availableIds, null);
                    }

                    return $value;
                })
                ->addDefaultChildrenIfNoneSet($availableIds)
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
                    $userClass = $config['class_mapping'][User::class];
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

                    if (isset($usernameLookup[$userClass])) {
                        throw new \LogicException(sprintf('Username lookup mapping for "%s" cannot be overwritten.', $userClass));
                    }

                    if (null !== ($usernameField = self::getUsernameField($userClass)) && $usernameLookup) {
                        $usernameLookup[$userClass][] = ['target' => $userClass, 'field' => $usernameField];
                    }

                    $config['username_field'] = $usernameField;
                    $config['username_lookup'] = $usernameLookup;

                    return $config;
                })
            ->end();

        return $treeBuilder;
    }

    private static function getUsernameField(string $class): ?string
    {
        if (null === $credential = (new \ReflectionMethod($class, 'getCredential'))->getReturnType()) {
            return null;
        }

        if ($credential->isBuiltin() || !is_subclass_of($credentialClass = $credential->getName(), CredentialInterface::class)) {
            throw new \LogicException(sprintf('Method "%s::getCredential()" must return a sub class of "%s", got "%s".', $class, CredentialInterface::class, $credential->getName()));
        }

        if ($credential->allowsNull()) {
            throw new \LogicException(sprintf('Method "%s::getCredential()" cannot be null-able.', $class));
        }

        return 'credential.'.$credentialClass::getUsernameField();
    }
}
