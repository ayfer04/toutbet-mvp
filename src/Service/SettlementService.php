<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Bet;
use App\Entity\Transaction;
use App\Entity\User;
use App\Repository\TransactionRepository;
use App\Repository\WagerRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Validation du résultat + distribution des gains en une seule transaction SQL.
 *
 * Règle de répartition (pot commun) : pot = somme des mises ; commission plateforme (5 %) et
 * commission du Bookie (fixée à la création, 10 % max) sont prélevées ; le reste est partagé entre
 * les gagnants au prorata de leur mise. S'il n'y a aucun gagnant, chaque mise est remboursée sans commission.
 * Les arrondis (centimes indivisibles) vont à la plateforme : la somme distribuée = le pot, au centime près.
 */
final class SettlementService
{
    public const PLATFORM_COMMISSION_BPS = 500;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly WagerRepository $wagers,
        private readonly TransactionRepository $ledger,
        private readonly AuditLogger $audit,
    ) {}

    public function settle(User $actor, string $betId, bool $outcome): Bet
    {
        return $this->entityManager->wrapInTransaction(function () use ($actor, $betId, $outcome): Bet {
            $bet = $this->entityManager->find(Bet::class, $betId, LockMode::PESSIMISTIC_WRITE);
            if (!$bet instanceof Bet || !$bet->isOwnedBy($actor)) {
                throw new \DomainException('Not found.');
            }
            // Lève une exception si déjà validé ou pas encore clos (STRIDE: Tampering).
            $bet->settle($outcome, new \DateTimeImmutable());

            $wagers = $this->wagers->findForBet($bet);
            $pot = 0;
            $winningStakes = 0;
            foreach ($wagers as $wager) {
                $pot += $wager->getStakeCents();
                if ($wager->getPrediction() === $outcome) {
                    $winningStakes += $wager->getStakeCents();
                }
            }

            if ($winningStakes === 0) {
                foreach ($wagers as $wager) {
                    $this->credit($wager->getBettor(), $bet, Transaction::REFUND, $wager->getStakeCents(), 'refund:' . $wager->getId());
                }
            } else {
                $platform = intdiv($pot * self::PLATFORM_COMMISSION_BPS, 10000);
                $bookie = intdiv($pot * $bet->getBookieCommissionBps(), 10000);
                $distributable = $pot - $platform - $bookie;
                $distributed = 0;
                foreach ($wagers as $wager) {
                    if ($wager->getPrediction() !== $outcome) {
                        continue;
                    }
                    $share = intdiv($distributable * $wager->getStakeCents(), $winningStakes);
                    $distributed += $share;
                    // Le bénéficiaire est toujours l'auteur de la mise, jamais un paramètre de la requête
                    // (abuse story : "modifier le bénéficiaire des gains").
                    $this->credit($wager->getBettor(), $bet, Transaction::PAYOUT, $share, 'payout:' . $wager->getId());
                }
                $this->credit($bet->getBookie(), $bet, Transaction::BOOKIE_COMMISSION, $bookie, 'bookie_commission:' . $bet->getId());
                $this->credit(null, $bet, Transaction::PLATFORM_COMMISSION, $platform + ($distributable - $distributed), 'platform_commission:' . $bet->getId());
            }

            $this->audit->log($actor, 'bet.settled', ['bet_id' => $bet->getId(), 'outcome' => $outcome, 'pot_cents' => $pot, 'wagers' => count($wagers)]);

            return $bet;
        });
    }

    private function credit(?User $user, Bet $bet, string $type, int $amountCents, string $idempotencyKey): void
    {
        // Idempotence : une écriture déjà passée n'est jamais rejouée.
        if ($amountCents <= 0 || $this->ledger->existsWithKey($idempotencyKey)) {
            return;
        }
        $this->entityManager->persist(new Transaction(Uuid::v4(), $user, $bet, $type, $amountCents, $idempotencyKey));
    }
}
