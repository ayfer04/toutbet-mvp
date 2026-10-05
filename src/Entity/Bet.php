<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\BetRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Pari binaire (oui / non). Montants en centimes, cotation en centièmes (250 = 2,50) : aucun float.
 */
#[ORM\Entity(repositoryClass: BetRepository::class)]
#[ORM\Table(name: 'bets')]
class Bet
{
    public const STATUS_OPEN = 'open';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_SETTLED = 'settled';

    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $bookie;

    #[ORM\Column(length: 140)]
    private string $title;

    #[ORM\Column]
    private int $minStakeCents;

    #[ORM\Column]
    private int $maxStakeCents;

    #[ORM\Column]
    private int $oddsHundredths;

    #[ORM\Column]
    private int $bookieCommissionBps;

    #[ORM\Column(length: 16)]
    private string $status = self::STATUS_OPEN;

    #[ORM\Column]
    private \DateTimeImmutable $closesAt;

    #[ORM\Column(nullable: true)]
    private ?bool $outcome = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $resultValidatedAt = null;

    #[ORM\Column]
    private int $wagerCount = 0;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $id, User $bookie, string $title, int $minStakeCents, int $maxStakeCents, int $oddsHundredths, int $bookieCommissionBps, \DateTimeImmutable $closesAt)
    {
        $this->id = $id;
        $this->bookie = $bookie;
        $this->title = $title;
        $this->minStakeCents = $minStakeCents;
        $this->maxStakeCents = $maxStakeCents;
        $this->oddsHundredths = $oddsHundredths;
        $this->bookieCommissionBps = $bookieCommissionBps;
        $this->closesAt = $closesAt;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): string { return $this->id; }
    public function getBookie(): User { return $this->bookie; }
    public function isOwnedBy(User $user): bool { return $this->bookie->getId() === $user->getId(); }
    public function getTitle(): string { return $this->title; }
    public function getMinStakeCents(): int { return $this->minStakeCents; }
    public function getMaxStakeCents(): int { return $this->maxStakeCents; }
    public function getOddsHundredths(): int { return $this->oddsHundredths; }
    public function getBookieCommissionBps(): int { return $this->bookieCommissionBps; }
    public function getStatus(): string { return $this->status; }
    public function getClosesAt(): \DateTimeImmutable { return $this->closesAt; }
    public function getOutcome(): ?bool { return $this->outcome; }
    public function getWagerCount(): int { return $this->wagerCount; }

    /** Un pari accepte des mises s'il est ouvert ET que l'heure de clôture n'est pas dépassée. */
    public function acceptsWagers(\DateTimeImmutable $now): bool
    {
        return $this->status === self::STATUS_OPEN && $now < $this->closesAt;
    }

    public function isClosedAt(\DateTimeImmutable $now): bool
    {
        return $this->status === self::STATUS_CLOSED || ($this->status === self::STATUS_OPEN && $now >= $this->closesAt);
    }

    // STRIDE: Tampering — cotation et plafonds modifiables uniquement avant la 1re mise.
    public function updateTerms(int $minStakeCents, int $maxStakeCents, int $oddsHundredths): void
    {
        if ($this->wagerCount > 0 || $this->status !== self::STATUS_OPEN) {
            throw new \DomainException('Terms are locked.');
        }
        $this->minStakeCents = $minStakeCents;
        $this->maxStakeCents = $maxStakeCents;
        $this->oddsHundredths = $oddsHundredths;
    }

    public function registerWager(): void
    {
        ++$this->wagerCount;
    }

    public function close(): void
    {
        if ($this->status !== self::STATUS_OPEN) {
            throw new \DomainException('Bet is not open.');
        }
        $this->status = self::STATUS_CLOSED;
    }

    // STRIDE: Tampering — résultat validable une seule fois, après clôture.
    public function settle(bool $outcome, \DateTimeImmutable $now): void
    {
        if ($this->status === self::STATUS_SETTLED || $this->resultValidatedAt !== null) {
            throw new \DomainException('Result already validated.');
        }
        if (!$this->isClosedAt($now)) {
            throw new \DomainException('Bet is not closed.');
        }
        $this->outcome = $outcome;
        $this->resultValidatedAt = $now;
        $this->status = self::STATUS_SETTLED;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'min_stake_cents' => $this->minStakeCents,
            'max_stake_cents' => $this->maxStakeCents,
            'odds_hundredths' => $this->oddsHundredths,
            'bookie_commission_bps' => $this->bookieCommissionBps,
            'status' => $this->status,
            'closes_at' => $this->closesAt->format(\DATE_ATOM),
            'outcome' => $this->outcome,
            'wager_count' => $this->wagerCount,
        ];
    }
}
