<?php

declare(strict_types=1);

namespace MsgPhp\UserBundle\DependencyInjection;

use MsgPhp\Domain\Infra\DependencyInjection\Bundle\ConfigHelper;
use MsgPhp\User\Entity\User;
use MsgPhp\User\Infra\Uuid\UserId;
use MsgPhp\User\UserIdInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * @author Roland Franssen <franssen.roland@gmail.com>
 */
final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder();

        $treeBuilder->root(Extension::ALIAS)
            ->append(ConfigHelper::createClassMappingNode('class_mapping', [
                User::class,
                UserIdInterface::class => 'Try `composer require ramsey/uuid`',
            ], function (array $value): array {
                if (class_exists(Uuid::class)) {
                    $value += [
                        UserIdInterface::class => UserId::class,
                    ];
                }

                return $value;
            }));

        return $treeBuilder;
    }
}
