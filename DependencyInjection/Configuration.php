<?php

declare(strict_types=1);

namespace MsgPhp\UserBundle\DependencyInjection;

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
        $rootNode = $treeBuilder->root(Extension::ALIAS);

        $rootNode
            ->children()
                ->arrayNode('class_mapping')
                    ->isRequired()
                    ->useAttributeAsKey('class')
                    ->scalarPrototype()
                        ->cannotBeEmpty()
                    ->end()
                    ->validate()
                        ->always()
                        ->then(function (array $value) {
                            if (class_exists(Uuid::class)) {
                                $value += [
                                    UserIdInterface::class => UserId::class,
                                ];
                            }

                            return $value;
                        })
                    ->end()
                    ->validate()
                        ->ifTrue(function (array $value) {
                            return !isset($value[User::class]);
                        })
                        ->thenInvalid(sprintf('Class "%s" must be configured', User::class))
                    ->end()
                    ->validate()
                        ->ifTrue(function (array $value) {
                            return !isset($value[UserIdInterface::class]);
                        })
                        ->thenInvalid(sprintf('Class "%s" must be configured. Try `composer require ramsey/uuid`', UserIdInterface::class))
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
