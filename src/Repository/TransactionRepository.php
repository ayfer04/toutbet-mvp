<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Transaction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Transaction> */
final class TransactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Transaction::class);
    }

    public function balanceOf(\App\Entity\User $user): int
    {
        // Requête paramétrée (STRIDE: Tampering / injection SQL impossible).
        return (int) $this->createQueryBuilder('t')
            ->select('COALESCE(SUM(t.amountCents), 0)')
            ->where('t.user = :user')
            ->setParameter('user', $user)
            ->getQuery()->getSingleScalarResult();
    }

    public function existsWithKey(string $idempotencyKey): bool
    {
        return $this->count(['idempotencyKey' => $idempotencyKey]) > 0;
    }

    /** @return list<Transaction> */
    public function findForUser(\App\Entity\User $user, int $page, int $perPage): array
    {
        return $this->findBy(['user' => $user], ['createdAt' => 'DESC'], $perPage, ($page - 1) * $perPage);
    }
}
