<?php

declare(strict_types=1);

namespace MsgPhp\UserBundle\DependencyInjection;

use MsgPhp\User\Entity\User;
use MsgPhp\User\Infra\Uuid\UserId;
use MsgPhp\User\UserIdInterface;
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
                    ->useAttributeAsKey('class')
                    ->scalarPrototype()
                        ->cannotBeEmpty()
                    ->end()
                    ->validate()
                        ->ifTrue(function (array $value) { return !isset($value[User::class]); })
                        ->thenInvalid(sprintf('Class "%s" must be configured', User::class))
                    ->end()
                    ->validate()
                        ->always()
                        ->then(function (array $value) {
                            return $value + [
                                UserIdInterface::class => UserId::class,
                            ];
                        })
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
