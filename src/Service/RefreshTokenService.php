<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final class RefreshTokenService
{
    private const TTL = 2592000; // 30 days

    public function __construct(private readonly EntityManagerInterface $entityManager) {}

    public function issue(User $user): string
    {
        $token = rtrim(strtr(base64_encode(random_bytes(64)), '+/', '-_'), '=');
        $expiresAt = new \DateTimeImmutable(sprintf('+%d seconds', self::TTL));
        $user->setRefreshToken(hash('sha256', $token), $expiresAt);
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        return $token;
    }

    public function rotate(User $user, string $presentedToken): string
    {
        $stored = $user->getRefreshTokenHash();
        $expiresAt = $user->getRefreshTokenExpiresAt();
        if ($stored === null || $expiresAt === null || $expiresAt <= new \DateTimeImmutable() || !hash_equals($stored, hash('sha256', $presentedToken))) {
            throw new \RuntimeException('Invalid refresh token.');
        }
        return $this->issue($user);
    }
}
