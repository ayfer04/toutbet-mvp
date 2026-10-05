<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;

/**
 * Implémentation de développement/test : garde le dernier jeton en mémoire (jamais loggé, jamais renvoyé par l'API).
 */
final class InMemoryEmailVerificationSender implements EmailVerificationSender
{
    /** @var array<string, string> */
    private array $tokens = [];

    public function send(User $user, string $rawToken): void
    {
        $this->tokens[$user->getEmail()] = $rawToken;
    }

    public function lastTokenFor(string $email): ?string
    {
        return $this->tokens[strtolower(trim($email))] ?? null;
    }
}
