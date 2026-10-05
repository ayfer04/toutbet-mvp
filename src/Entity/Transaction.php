<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TransactionRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Grand livre (ledger) en écritures uniquement : le solde d'un utilisateur est la somme de ses écritures.
 * La clé d'idempotence unique empêche qu'une même opération (webhook rejoué, double règlement) soit comptée deux fois.
 */
#[ORM\Entity(repositoryClass: TransactionRepository::class)]
#[ORM\Table(name: 'ledger_transactions')]
#[ORM\UniqueConstraint(name: 'uniq_ledger_idempotency_key', columns: ['idempotency_key'])]
class Transaction
{
    public const DEPOSIT = 'deposit';
    public const STAKE_ESCROW = 'stake_escrow';
    public const PAYOUT = 'payout';
    public const REFUND = 'refund';
    public const BOOKIE_COMMISSION = 'bookie_commission';
    public const PLATFORM_COMMISSION = 'platform_commission';

    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $user;

    #[ORM\ManyToOne(targetEntity: Bet::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Bet $bet;

    #[ORM\Column(length: 32)]
    private string $type;

    #[ORM\Column(type: 'bigint')]
    private int|string $amountCents;

    #[ORM\Column(length: 128)]
    private string $idempotencyKey;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $externalReference;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $id, ?User $user, ?Bet $bet, string $type, int $amountCents, string $idempotencyKey, ?string $externalReference = null)
    {
        $this->id = $id;
        $this->user = $user;
        $this->bet = $bet;
        $this->type = $type;
        $this->amountCents = (string) $amountCents;
        $this->idempotencyKey = $idempotencyKey;
        $this->externalReference = $externalReference;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getType(): string { return $this->type; }
    public function getAmountCents(): int { return (int) $this->amountCents; }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'amount_cents' => (int) $this->amountCents,
            'bet_id' => $this->bet?->getId(),
            'created_at' => $this->createdAt->format(\DATE_ATOM),
        ];
    }
}
