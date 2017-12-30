<?php

declare(strict_types=1);

use MsgPhp\User\PasswordEncoderInterface;
use MsgPhp\User\Infra\Security\{PasswordEncoder, SecurityUser, SecurityUserFactory, SecurityUserProvider};
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Security\Core\Encoder\PasswordEncoderInterface as SymfonyPasswordEncoderInterface;
use function Symfony\Component\DependencyInjection\Loader\Configurator\{inline, ref};

return function (ContainerConfigurator $container): void {
    $container->services()
        ->defaults()
            ->autowire()
            ->private()
        ->set(PasswordEncoder::class)
            ->args([
                inline(SymfonyPasswordEncoderInterface::class)
                    ->factory([ref('security.encoder_factory'), 'getEncoder'])
                    ->args([SecurityUser::class]),
            ])
        ->alias(PasswordEncoderInterface::class, PasswordEncoder::class)
        ->set(SecurityUserFactory::class)
        ->set(SecurityUserProvider::class)
    ;
};
