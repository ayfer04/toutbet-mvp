<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

final class EmailVerificationService
{
    private const TTL = 86400; // 24 h

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $users,
        private readonly EmailVerificationSender $sender,
    ) {}

    public function start(User $user): void
    {
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        // STRIDE: Information Disclosure — seul le hash du jeton est stocké.
        $user->setEmailVerificationToken(hash('sha256', $token), new \DateTimeImmutable(sprintf('+%d seconds', self::TTL)));
        $this->entityManager->flush();
        $this->sender->send($user, $token);
    }

    public function verify(string $rawToken): bool
    {
        if ($rawToken === '') {
            return false;
        }
        $user = $this->users->findByEmailVerificationTokenHash(hash('sha256', $rawToken));
        $expiresAt = $user?->getEmailVerificationExpiresAt();
        if ($user === null || $expiresAt === null || $expiresAt <= new \DateTimeImmutable()) {
            return false;
        }
        $user->markEmailVerified();
        $this->entityManager->flush();

        return true;
    }
}
