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

use MsgPhp\UserBundle\DependencyInjection\Configuration;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
            ->autowire()
            ->private()
    ;

    foreach (Configuration::getPackageMetadata()->getMessageServicePrototypes() as $resource => $namespace) {
        $prototype = $services->load($namespace, $resource);
        if (Configuration::PACKAGE_NS.'Command\\Handler\\' === $namespace) {
            $prototype->tag('msgphp.domain.command_handler');
        }
    }
};
