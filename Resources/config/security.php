<?php

declare(strict_types=1);

use Lexik\Bundle\JWTAuthenticationBundle\Security\User\PayloadAwareUserProviderInterface;
use MsgPhp\User\Infrastructure\Security;
use MsgPhp\User\Password\PasswordHashing;
use Sensio\Bundle\FrameworkExtraBundle\Request\ParamConverter\ParamConverterInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\ref;
use Symfony\Component\Security\Core\Encoder\PasswordEncoderInterface;

return function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
            ->autowire()
            ->private()

        ->set(Security\HashingFactory::class)
            ->decorate('security.encoder_factory')

        ->set('.msgphp_user.security.password_hashing', PasswordEncoderInterface::class)
            ->factory([ref('security.encoder_factory'), 'getEncoder'])
            ->args([Security\SecurityUser::class])
        ->alias(PasswordEncoderInterface::class, '.msgphp_user.security.password_hashing')

        ->set(Security\PasswordHashing::class)
            ->arg('$encoder', ref('.msgphp_user.security.password_hashing'))
        ->alias(PasswordHashing::class, Security\PasswordHashing::class)

        ->set(Security\SecurityUserProvider::class)
        ->set(Security\UserArgumentValueResolver::class)
            ->tag('controller.argument_value_resolver')
    ;

    if (interface_exists(ParamConverterInterface::class)) {
        $services->set(Security\UserParamConverter::class)
            ->tag('request.param_converter', ['converter' => Security\UserParamConverter::NAME])
        ;
    }

    if (interface_exists(PayloadAwareUserProviderInterface::class)) {
        $services->set(Security\Jwt\SecurityUserProvider::class);
    }
};
