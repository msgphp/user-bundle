<?php

use MsgPhp\User\PasswordEncoderInterface;
use MsgPhp\User\Infra\Security\PasswordEncoder;
use MsgPhp\User\Infra\Security\SecurityUser;
use MsgPhp\User\Infra\Security\SecurityUserChecker;
use MsgPhp\User\Infra\Security\SecurityUserFactory;
use MsgPhp\User\Infra\Security\SecurityUserProvider;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Security\Core\Encoder\PasswordEncoderInterface as SymfonyPasswordEncoderInterface;
use function Symfony\Component\DependencyInjection\Loader\Configurator\{inline, ref};

return function (ContainerConfigurator $container) {
    $container->services()
        ->defaults()
            ->autowire()
            ->private()
        ->set(PasswordEncoder::class)
            ->args([
                inline(SymfonyPasswordEncoderInterface::class)
                    ->factory([ref('security.encoder_factory'), 'getEncoder'])
                    ->args([SecurityUser::class])
            ])
        ->alias(PasswordEncoderInterface::class, PasswordEncoder::class)
        ->set(SecurityUserChecker::class)
        ->set(SecurityUserFactory::class)
        ->set(SecurityUserProvider::class)
    ;
};
