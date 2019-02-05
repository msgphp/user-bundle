<?php

declare(strict_types=1);

/*
 * This file is part of the MsgPHP package.
 *
 * (c) Roland Franssen <franssen.roland@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events as DoctrineOrmEvents;
use MsgPhp\User\Infra\Doctrine;
use MsgPhp\UserBundle\DependencyInjection\Configuration;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\ref;

return function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
            ->autowire()
            ->autoconfigure()
            ->private()
            ->bind(EntityManagerInterface::class, ref('msgphp.doctrine.entity_manager'))

        ->set(Doctrine\Event\UsernameListener::class)
            ->tag('doctrine.orm.entity_listener')
            ->tag('doctrine.event_listener', ['event' => DoctrineOrmEvents::loadClassMetadata])
            ->tag('doctrine.event_listener', ['event' => DoctrineOrmEvents::postFlush])
    ;

    foreach (Configuration::getPackageMetadata()->getDoctrineServicePrototypes() as $resource => $namespace) {
        $services->load($namespace, $resource);
    }
};
