<?php

declare(strict_types=1);

/*
 * This file is part of the MsgPHP package.
 *
 * (c) Roland Franssen <franssen.roland@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MsgPhp\UserBundle\Twig;

use MsgPhp\User\Entity\User;
use MsgPhp\User\Infra\Security\SecurityUser;
use MsgPhp\User\Repository\UserRepositoryInterface;
use MsgPhp\User\UserIdInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * @author Roland Franssen <franssen.roland@gmail.com>
 *
 * @internal
 */
final class GlobalVariable
{
    public const NAME = 'msgphp_user';

    /**
     * @var ContainerInterface
     */
    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function getCurrent(): User
    {
        return $this->getUserRepository()->find($this->getCurrentId());
    }

    public function getCurrentId(): UserIdInterface
    {
        $token = $this->getTokenStorage()->getToken();

        if (null === $token) {
            throw new \LogicException('User not authenticated.');
        }

        $user = $token->getUser();

        if (!$user instanceof SecurityUser) {
            throw new \LogicException('User not authenticated.');
        }

        return $user->getUserId();
    }

    public function isUserType(User $user, string $class): bool
    {
        return $user instanceof $class;
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
