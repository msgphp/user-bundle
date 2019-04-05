<?php

declare(strict_types=1);

use MsgPhp\User\Repository\UserRepository;
use MsgPhp\UserBundle\Twig;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\inline;
use function Symfony\Component\DependencyInjection\Loader\Configurator\ref;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

return static function (ContainerConfigurator $container): void {
    $container->services()
        ->defaults()
            ->private()

        ->set(Twig\GlobalVariable::class)
            ->args([
                inline(ServiceLocator::class)
                    ->args([[
                        TokenStorageInterface::class => ref(TokenStorageInterface::class)->nullOnInvalid(),
                        UserRepository::class => ref(UserRepository::class)->nullOnInvalid(),
                    ]])
                    ->tag('container.service_locator'),
            ])
    ;
};
