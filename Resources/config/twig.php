<?php

declare(strict_types=1);

use MsgPhp\User\Repository\UserRepositoryInterface;
use MsgPhp\UserBundle\Twig;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use function Symfony\Component\DependencyInjection\Loader\Configurator\{inline, ref};

return function (ContainerConfigurator $container): void {
    $container->services()
        ->defaults()
            ->private()

        ->set(Twig\GlobalVariable::class)
            ->args([
                inline(ServiceLocator::class)
                    ->args([[
                        TokenStorageInterface::class => ref(TokenStorageInterface::class)->nullOnInvalid(),
                        UserRepositoryInterface::class => ref(UserRepositoryInterface::class)->nullOnInvalid(),
                    ]])
                    ->tag('container.service_locator'),
            ])
    ;
};
