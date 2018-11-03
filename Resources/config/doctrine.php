<?php

declare(strict_types=1);

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events as DoctrineOrmEvents;
use MsgPhp\User\Infra\Doctrine;
use MsgPhp\UserBundle\DependencyInjection\Configuration;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\ref;

return function (ContainerConfigurator $container): void {
    $container->services()
        ->defaults()
            ->autowire()
            ->autoconfigure()
            ->private()

        ->load('MsgPhp\\User\\Infra\\Doctrine\\Repository\\', Configuration::getPackageGlob().'/Infra/Doctrine/Repository/*Repository.php')
            ->bind(EntityManagerInterface::class, ref('msgphp.doctrine.entity_manager'))

        ->set(Doctrine\ObjectMappings::class)

        ->set(Doctrine\Event\UsernameListener::class)
            ->tag('doctrine.orm.entity_listener')
            ->tag('doctrine.event_listener', ['event' => DoctrineOrmEvents::loadClassMetadata])
            ->tag('doctrine.event_listener', ['event' => DoctrineOrmEvents::postFlush])
    ;
};
