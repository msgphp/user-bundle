<?php

declare(strict_types=1);

use Lexik\Bundle\JWTAuthenticationBundle\Security\User\PayloadAwareUserProviderInterface;
use MsgPhp\User\Infrastructure\Security;
use Sensio\Bundle\FrameworkExtraBundle\Request\ParamConverter\ParamConverterInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Security\Core\Encoder\PasswordEncoderInterface;

return static function (ContainerConfigurator $container): void {
    $service = function_exists('Symfony\Component\DependencyInjection\Loader\Configurator\service')
        ? 'Symfony\Component\DependencyInjection\Loader\Configurator\service'
        : 'Symfony\Component\DependencyInjection\Loader\Configurator\ref';

    $services = $container->services()
        ->defaults()
            ->autowire()
            ->private()

        ->set(PasswordEncoderInterface::class)
            ->factory([$service('security.encoder_factory'), 'getEncoder'])
            ->args([Security\UserIdentity::class])

        ->set(Security\UserIdentityProvider::class)
        ->set(Security\UserArgumentValueResolver::class)
            ->tag('controller.argument_value_resolver')
    ;

    if (interface_exists(ParamConverterInterface::class)) {
        $services->set(Security\UserParamConverter::class)
            ->tag('request.param_converter', ['converter' => Security\UserParamConverter::NAME])
        ;
    }

    if (interface_exists(PayloadAwareUserProviderInterface::class)) {
        $services->set(Security\Jwt\UserIdentityProvider::class);
    }
};
