<?php

declare(strict_types=1);

namespace MsgPhp\UserBundle\Twig;

use MsgPhp\Domain\Factory\EntityAwareFactoryInterface;
use MsgPhp\User\Entity\User;
use MsgPhp\User\Infra\Security\SecurityUser;
use MsgPhp\User\Repository\UserRepositoryInterface;
use MsgPhp\User\UserIdInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ServiceSubscriberInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * @author Roland Franssen <franssen.roland@gmail.com>
 *
 * @internal
 */
final class GlobalVariables implements ServiceSubscriberInterface
{
    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function getUser(): ?User
    {
        $id = $this->getUserId();

        return null === $id ? null : $this->getRepository()->find($id);
    }

    public function getUserId(): ?UserIdInterface
    {
        $token = $this->getTokenStorage()->getToken();

        if (null === $token) {
            return null;
        }

        $user = $token->getUser();

        if (!$user instanceof SecurityUser) {
            return null;
        }

        /** @var UserIdInterface $id */
        $id = $this->getDomainFactory()->identify(User::class, $user->getUsername());

        return $id;
    }

    public static function getSubscribedServices(): array
    {
        return [
            EntityAwareFactoryInterface::class,
            '?'.TokenStorageInterface::class,
            '?'.UserRepositoryInterface::class,
        ];
    }

    private function getDomainFactory(): EntityAwareFactoryInterface
    {
        return $this->container->get(EntityAwareFactoryInterface::class);
    }

    private function getTokenStorage(): TokenStorageInterface
    {
        if (!$this->container->has(TokenStorageInterface::class)) {
            throw new \LogicException('No token storage available.');
        }

        return $this->container->get(TokenStorageInterface::class);
    }

    private function getRepository(): UserRepositoryInterface
    {
        if (!$this->container->has(UserRepositoryInterface::class)) {
            throw new \LogicException('No repository available.');
        }

        return $this->container->get(UserRepositoryInterface::class);
    }
}
