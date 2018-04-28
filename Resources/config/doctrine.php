<?php

declare(strict_types=1);

use Doctrine\ORM\Events as DoctrineOrmEvents;
use MsgPhp\User\Infra\Doctrine;
use MsgPhp\UserBundle\DependencyInjection\Configuration;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $container): void {
    $container->services()
        ->defaults()
            ->autowire()
            ->private()

        ->load('MsgPhp\\User\\Infra\\Doctrine\\Repository\\', Configuration::getPackageDir().'/Infra/Doctrine/Repository/*Repository.php')

        ->set(Doctrine\Event\UsernameListener::class)
            ->tag('doctrine.orm.entity_listener')
            ->tag('doctrine.event_listener', ['event' => DoctrineOrmEvents::loadClassMetadata])
            ->tag('doctrine.event_listener', ['event' => DoctrineOrmEvents::postFlush])
    ;
};
