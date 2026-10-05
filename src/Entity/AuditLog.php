<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * STRIDE: Repudiation — journal append-only (UPDATE/DELETE interdits par trigger SQL),
 * chaque entrée contient le hash de la précédente : toute altération casse la chaîne.
 */
#[ORM\Entity]
#[ORM\Table(name: 'audit_logs')]
class AuditLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $sequence = null;

    #[ORM\Column(type: 'guid', nullable: true)]
    private ?string $actorId;

    #[ORM\Column(length: 64)]
    private string $action;

    #[ORM\Column(type: 'json')]
    private array $payload;

    #[ORM\Column(length: 64)]
    private string $previousHash;

    #[ORM\Column(length: 64, unique: true)]
    private string $hash;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** @param array<string, mixed> $payload */
    public function __construct(?string $actorId, string $action, array $payload, string $previousHash, \DateTimeImmutable $createdAt)
    {
        $this->actorId = $actorId;
        $this->action = $action;
        $this->payload = $payload;
        $this->previousHash = $previousHash;
        $this->createdAt = $createdAt;
        $this->hash = self::computeHash($previousHash, $actorId, $action, $payload, $createdAt);
    }

    /** @param array<string, mixed> $payload */
    public static function computeHash(string $previousHash, ?string $actorId, string $action, array $payload, \DateTimeImmutable $createdAt): string
    {
        return hash('sha256', $previousHash . '|' . ($actorId ?? '-') . '|' . $action . '|' . json_encode($payload, JSON_THROW_ON_ERROR) . '|' . $createdAt->format('Y-m-d\TH:i:s'));
    }

    public function getHash(): string { return $this->hash; }
    public function getPreviousHash(): string { return $this->previousHash; }
    public function getActorId(): ?string { return $this->actorId; }
    public function getAction(): string { return $this->action; }
    /** @return array<string, mixed> */
    public function getPayload(): array { return $this->payload; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
