<?php

declare(strict_types=1);

use MsgPhp\User\Repository\UserRepository;
use MsgPhp\UserBundle\Twig;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

return static function (ContainerConfigurator $container): void {
    $service = function_exists('Symfony\Component\DependencyInjection\Loader\Configurator\service')
        ? 'Symfony\Component\DependencyInjection\Loader\Configurator\service'
        : 'Symfony\Component\DependencyInjection\Loader\Configurator\ref';
    $inlineService = function_exists('Symfony\Component\DependencyInjection\Loader\Configurator\inline_service')
        ? 'Symfony\Component\DependencyInjection\Loader\Configurator\inline_service'
        : 'Symfony\Component\DependencyInjection\Loader\Configurator\inline';

    $container->services()
        ->defaults()
            ->private()

        ->set(Twig\GlobalVariable::class)
            ->args([
                $inlineService(ServiceLocator::class)
                    ->args([[
                        TokenStorageInterface::class => $service(TokenStorageInterface::class)->nullOnInvalid(),
                        UserRepository::class => $service(UserRepository::class)->nullOnInvalid(),
                    ]])
                    ->tag('container.service_locator'),
            ])
    ;
};
