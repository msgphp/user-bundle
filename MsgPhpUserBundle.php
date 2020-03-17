<?php

declare(strict_types=1);

namespace MsgPhp\UserBundle;

use MsgPhp\Domain\Infrastructure\DependencyInjection\BundleHelper;
use MsgPhp\UserBundle\DependencyInjection\Compiler\CleanupPass;
use MsgPhp\UserBundle\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
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
        $container->addCompilerPass(new CleanupPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 100);

        BundleHelper::build($container);
    }

    public function getContainerExtension(): ?ExtensionInterface
    {
        return new Extension();
    }
}
