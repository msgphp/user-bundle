<?php

declare(strict_types=1);

use Doctrine\ORM\Events as DoctrineOrmEvents;
use MsgPhp\Domain\Infra\DependencyInjection\ContainerHelper;
use MsgPhp\User\Infra\Doctrine;
use MsgPhp\User\UserIdInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/** @var ContainerBuilder $container */
$container = $container ?? (function (): ContainerBuilder { throw new \LogicException('Invalid context.'); })();
$reflector = ContainerHelper::getClassReflector($container);

return function (ContainerConfigurator $container) use ($reflector): void {
    $baseDir = dirname($reflector(UserIdInterface::class)->getFileName());
    $services = $container->services()
        ->defaults()
            ->autowire()
            ->private()

        ->load($ns = 'MsgPhp\\User\\Infra\\Doctrine\\Repository\\', $repositories = $baseDir.'/Infra/Doctrine/Repository/*Repository.php')

        ->set(Doctrine\Event\UsernameListener::class)
            ->tag('doctrine.orm.entity_listener')
            ->tag('doctrine.event_listener', ['event' => DoctrineOrmEvents::loadClassMetadata])
            ->tag('doctrine.event_listener', ['event' => DoctrineOrmEvents::postFlush])
    ;

    foreach (glob($repositories) as $file) {
        foreach ($reflector($repository = $ns.basename($file, '.php'))->getInterfaceNames() as $interface) {
            $services->alias($interface, $repository);
        }
    }
};
