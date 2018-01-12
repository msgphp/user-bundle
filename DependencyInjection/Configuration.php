<?php

declare(strict_types=1);

namespace MsgPhp\UserBundle\DependencyInjection;

use MsgPhp\Domain\Infra\DependencyInjection\Bundle\ConfigHelper;
use MsgPhp\User\Entity\{PendingUser, User, UserAttributeValue, UserRole, UserSecondaryEmail};
use MsgPhp\User\Infra\Uuid;
use MsgPhp\User\{UserId, UserIdInterface};
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * @author Roland Franssen <franssen.roland@gmail.com>
 */
final class Configuration implements ConfigurationInterface
{
    public const IDENTITY_MAP = [
        PendingUser::class => 'email',
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
                    return $value + array_fill_keys($availableIds, null);
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
            );

        return $treeBuilder;
    }
}
