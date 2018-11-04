<?php

declare(strict_types=1);

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

        ->set(Doctrine\Event\UsernameListener::class)
            ->tag('doctrine.orm.entity_listener')
            ->tag('doctrine.event_listener', ['event' => DoctrineOrmEvents::loadClassMetadata])
            ->tag('doctrine.event_listener', ['event' => DoctrineOrmEvents::postFlush])
    ;

    foreach (Configuration::getPackageDirs() as $dir) {
        $services
            ->load(Configuration::PACKAGE_NS.'Infra\\Doctrine\\Repository\\', $dir.'/Infra/Doctrine/Repository/*Repository.php')
                ->bind(EntityManagerInterface::class, ref('msgphp.doctrine.entity_manager'))

            ->load(Configuration::PACKAGE_NS.'Infra\\Doctrine\\', $dir.'/Infra/Doctrine/*ObjectMappings.php')
        ;
    }
};
