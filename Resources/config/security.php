<?php

declare(strict_types=1);

use Lexik\Bundle\JWTAuthenticationBundle\Security\User\UserProviderWithPayloadSupportsInterface;
use MsgPhp\User\Password\PasswordHashingInterface;
use MsgPhp\User\Infra\Security;
use Sensio\Bundle\FrameworkExtraBundle\Request\ParamConverter\ParamConverterInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Security\Core\Encoder\PasswordEncoderInterface as SymfonyPasswordEncoderInterface;
use function Symfony\Component\DependencyInjection\Loader\Configurator\{inline, ref};

return function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
            ->autowire()
            ->private()

        ->set(Security\PasswordHashing::class)
            ->args([
                inline(SymfonyPasswordEncoderInterface::class)
                    ->factory([ref('security.encoder_factory'), 'getEncoder'])
                    ->args([Security\SecurityUser::class]),
            ])
        ->alias(PasswordHashingInterface::class, Security\PasswordHashing::class)

        ->set(Security\SecurityUserProvider::class)
        ->set(Security\UserValueResolver::class)
            ->tag('controller.argument_value_resolver')
    ;

    if (interface_exists(ParamConverterInterface::class)) {
        $services->set(Security\UserParamConverter::class)
            ->tag('request.param_converter', ['converter' => Security\UserParamConverter::NAME]);
    }

    if (interface_exists(UserProviderWithPayloadSupportsInterface::class)) {
        $services->set(Security\Jwt\SecurityUserProvider::class);
    }
};
