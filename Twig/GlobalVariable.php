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
final class GlobalVariable implements ServiceSubscriberInterface
{
    public const NAME = 'msgphp_user';

    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function getCurrent(): User
    {
        return $this->getUserRepository()->find($this->getId());
    }

    public function getId(): UserIdInterface
    {
        $token = $this->getTokenStorage()->getToken();

        if (null === $token) {
            throw new \LogicException('User not authenticated.');
        }

        $user = $token->getUser();

        if (!$user instanceof SecurityUser) {
            throw new \LogicException('User not authenticated.');
        }

        /** @var UserIdInterface $id */
        $id = $this->getObjectFactory()->identify(User::class, $user->getUsername());

        return $id;
    }

    public function isUserType(User $user, string $class): bool
    {
        return $user instanceof $class;
    }

    public static function getSubscribedServices(): array
    {
        return [
            EntityAwareFactoryInterface::class,
            '?'.TokenStorageInterface::class,
            '?'.UserRepositoryInterface::class,
        ];
    }

    private function getObjectFactory(): EntityAwareFactoryInterface
    {
        return $this->container->get(EntityAwareFactoryInterface::class);
    }

    private function getTokenStorage(): TokenStorageInterface
    {
        if (!$this->container->has(TokenStorageInterface::class)) {
            throw new \LogicException('Token storage not available.');
        }

        return $this->container->get(TokenStorageInterface::class);
    }

    private function getUserRepository(): UserRepositoryInterface
    {
        if (!$this->container->has(UserRepositoryInterface::class)) {
            throw new \LogicException('User repository not available.');
        }

        return $this->container->get(UserRepositoryInterface::class);
    }
}
