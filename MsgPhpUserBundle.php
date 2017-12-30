<?php

declare(strict_types=1);

namespace MsgPhp\UserBundle;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use MsgPhp\Domain\Infra\DependencyInjection\Compiler\DoctrineObjectFieldMappingPass;
use MsgPhp\UserBundle\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * @author Roland Franssen <franssen.roland@gmail.com>
 */
final class MsgPhpUserBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        $bundles = array_flip($container->getParameter('kernel.bundles'));
        $passes = array_flip(array_map(function (CompilerPassInterface $pass) {
            return get_class($pass);
        }, $container->getCompiler()->getPassConfig()->getPasses()));

        if (isset($bundles[DoctrineBundle::class])) {
            if (!isset($passes[DoctrineObjectFieldMappingPass::class])) {
                $container->addCompilerPass(new DoctrineObjectFieldMappingPass());
            }
        }
    }

    public function getContainerExtension(): ?ExtensionInterface
    {
        return new Extension();
    }
}
