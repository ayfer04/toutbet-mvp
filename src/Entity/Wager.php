<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\WagerRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Une mise est immuable : aucun setter, aucun endpoint de modification (STRIDE: Tampering).
 */
#[ORM\Entity(repositoryClass: WagerRepository::class)]
#[ORM\Table(name: 'wagers')]
#[ORM\UniqueConstraint(name: 'uniq_wager_bet_bettor', columns: ['bet_id', 'bettor_id'])]
class Wager
{
    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $id;

    #[ORM\ManyToOne(targetEntity: Bet::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Bet $bet;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $bettor;

    #[ORM\Column]
    private bool $prediction;

    #[ORM\Column]
    private int $stakeCents;

    #[ORM\Column]
    private int $oddsSnapshotHundredths;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $id, Bet $bet, User $bettor, bool $prediction, int $stakeCents)
    {
        $this->id = $id;
        $this->bet = $bet;
        $this->bettor = $bettor;
        $this->prediction = $prediction;
        $this->stakeCents = $stakeCents;
        $this->oddsSnapshotHundredths = $bet->getOddsHundredths();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): string { return $this->id; }
    public function getBet(): Bet { return $this->bet; }
    public function getBettor(): User { return $this->bettor; }
    public function getPrediction(): bool { return $this->prediction; }
    public function getStakeCents(): int { return $this->stakeCents; }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'bet_id' => $this->bet->getId(),
            'prediction' => $this->prediction,
            'stake_cents' => $this->stakeCents,
            'odds_snapshot_hundredths' => $this->oddsSnapshotHundredths,
            'created_at' => $this->createdAt->format(\DATE_ATOM),
        ];
    }
}
