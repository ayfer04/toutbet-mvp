<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Bet;
use App\Entity\Transaction;
use App\Entity\User;
use App\Entity\Wager;
use App\Repository\InvitationRepository;
use App\Repository\TransactionRepository;
use App\Repository\WagerRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final class WagerService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly InvitationRepository $invitations,
        private readonly WagerRepository $wagers,
        private readonly TransactionRepository $ledger,
        private readonly AuditLogger $audit,
    ) {}

    /** @throws WagerRejected */
    public function place(User $bettor, string $betId, bool $prediction, int $stakeCents): Wager
    {
        return $this->entityManager->wrapInTransaction(function () use ($bettor, $betId, $prediction, $stakeCents): Wager {
            // Verrouillage pessimiste : le pari et le portefeuille ne peuvent pas changer pendant la mise
            // (STRIDE: Tampering — pas de mise pendant la clôture, pas de double dépense).
            $bet = $this->entityManager->find(Bet::class, $betId, LockMode::PESSIMISTIC_WRITE);
            if (!$bet instanceof Bet) {
                throw new WagerRejected('not_found');
            }
            $this->entityManager->lock($bettor, LockMode::PESSIMISTIC_WRITE);

            if ($bet->isOwnedBy($bettor)) {
                throw new WagerRejected('bookie_cannot_bet');
            }
            if (!$bettor->isEmailVerified()) {
                throw new WagerRejected('email_not_verified');
            }
            if ($this->invitations->findActive($bet, $bettor) === null) {
                // Même réponse qu'un pari inexistant : on ne révèle pas l'existence du pari (anti-IDOR).
                throw new WagerRejected('not_found');
            }
            if (!$bet->acceptsWagers(new \DateTimeImmutable())) {
                throw new WagerRejected('bet_closed');
            }
            if ($stakeCents < $bet->getMinStakeCents() || $stakeCents > $bet->getMaxStakeCents()) {
                throw new WagerRejected('stake_out_of_bounds');
            }
            if ($this->wagers->findOneBy(['bet' => $bet, 'bettor' => $bettor]) !== null) {
                throw new WagerRejected('already_wagered');
            }
            if ($this->ledger->balanceOf($bettor) < $stakeCents) {
                throw new WagerRejected('insufficient_funds');
            }

            $wager = new Wager(Uuid::v4(), $bet, $bettor, $prediction, $stakeCents);
            $bet->registerWager();
            $this->entityManager->persist($wager);
            // Séquestre : la mise sort du solde du parieur et reste bloquée jusqu'au règlement.
            $this->entityManager->persist(new Transaction(Uuid::v4(), $bettor, $bet, Transaction::STAKE_ESCROW, -$stakeCents, 'escrow:' . $wager->getId()));
            $this->audit->log($bettor, 'wager.placed', ['bet_id' => $bet->getId(), 'wager_id' => $wager->getId(), 'prediction' => $prediction, 'stake_cents' => $stakeCents]);

            return $wager;
        });
    }
}
