<?php

declare(strict_types=1);

namespace MsgPhp\UserBundle;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use MsgPhp\Domain\Infra\DependencyInjection\Bundle\{BundleHelper, ContainerHelper};
use MsgPhp\Domain\Infra\DependencyInjection\Compiler\DoctrineObjectFieldMappingPass;
use MsgPhp\UserBundle\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * @author Roland Franssen <franssen.roland@gmail.com>
 */
final class MsgPhpUserBundle extends Bundle
{
    public function boot(): void
    {
        BundleHelper::prepareDoctrineTypes($this->container);
    }

    public function build(ContainerBuilder $container): void
    {
        $bundles = ContainerHelper::getBundles($container);

        if (isset($bundles[DoctrineBundle::class])) {
            ContainerHelper::addCompilerPassOnce($container, DoctrineObjectFieldMappingPass::class);
        }
    }

    public function getContainerExtension(): ?ExtensionInterface
    {
        return new Extension();
    }
}
