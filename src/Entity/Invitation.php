<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\InvitationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InvitationRepository::class)]
#[ORM\Table(name: 'invitations')]
#[ORM\UniqueConstraint(name: 'uniq_invitation_bet_invitee', columns: ['bet_id', 'invitee_id'])]
class Invitation
{
    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $id;

    #[ORM\ManyToOne(targetEntity: Bet::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Bet $bet;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $invitee;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $revokedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $id, Bet $bet, User $invitee)
    {
        $this->id = $id;
        $this->bet = $bet;
        $this->invitee = $invitee;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): string { return $this->id; }
    public function getBet(): Bet { return $this->bet; }
    public function getInvitee(): User { return $this->invitee; }
    public function isActive(): bool { return $this->revokedAt === null; }
    public function revoke(): void { $this->revokedAt ??= new \DateTimeImmutable(); }
}
