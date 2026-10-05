<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\AuditLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * STRIDE: Repudiation — chaque action sensible (pari, mise, résultat, distribution, dépôt) est tracée,
 * horodatée et chaînée par hash à l'entrée précédente.
 */
final class AuditLogger
{
    private const GENESIS = '0000000000000000000000000000000000000000000000000000000000000000';

    public function __construct(private readonly EntityManagerInterface $entityManager) {}

    /** @param array<string, mixed> $payload */
    public function log(?User $actor, string $action, array $payload): void
    {
        $connection = $this->entityManager->getConnection();
        if ($connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\PostgreSQLPlatform) {
            // Sérialise les écritures du journal pour garantir une chaîne linéaire, même en concurrence.
            $connection->executeStatement('SELECT pg_advisory_xact_lock(424242)');
        }
        $previous = $connection->fetchOne('SELECT hash FROM audit_logs ORDER BY sequence DESC LIMIT 1');
        $now = new \DateTimeImmutable();

        $this->entityManager->persist(new AuditLog($actor?->getId(), $action, $payload, is_string($previous) ? $previous : self::GENESIS, $now));
    }

    /** Vérifie l'intégrité complète de la chaîne. */
    public function verifyChain(): bool
    {
        $previous = self::GENESIS;
        foreach ($this->entityManager->getRepository(AuditLog::class)->findBy([], ['sequence' => 'ASC']) as $entry) {
            if ($entry->getPreviousHash() !== $previous
                || AuditLog::computeHash($previous, $entry->getActorId(), $entry->getAction(), $entry->getPayload(), $entry->getCreatedAt()) !== $entry->getHash()) {
                return false;
            }
            $previous = $entry->getHash();
        }

        return true;
    }
}
