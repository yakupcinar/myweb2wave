<?php

declare(strict_types=1);

namespace App\Panel;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Panelin tek yöneticisi ortam değişkeninden: ad + şifre ÖZETİ (düz şifre
 * hiçbir yerde durmaz; özet `bin/console security:hash-password` ile).
 * Biri boşsa kimse giremez — boş şifreyle giriş açılmasın.
 *
 * @implements UserProviderInterface<InMemoryUser>
 */
final readonly class YoneticiSaglayici implements UserProviderInterface
{
    public function __construct(
        #[Autowire('%env(PANEL_KULLANICI)%')]
        private string $ad,
        #[Autowire('%env(PANEL_SIFRE_OZETI)%')]
        private string $sifreOzeti,
    ) {
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        if ('' === $this->ad || '' === $this->sifreOzeti || !hash_equals($this->ad, $identifier)) {
            throw new UserNotFoundException();
        }

        return new InMemoryUser($this->ad, $this->sifreOzeti, ['ROLE_YONETICI']);
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof InMemoryUser) {
            throw new UnsupportedUserException();
        }

        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return InMemoryUser::class === $class;
    }
}
