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
