<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Bet;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Bet> */
final class BetRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Bet::class);
    }

    /** @return list<Bet> Paris visibles : ceux dont l'utilisateur est Bookie ou invité actif. */
    public function findVisibleTo(\App\Entity\User $user, int $page, int $perPage): array
    {
        return $this->createQueryBuilder('b')
            ->leftJoin(\App\Entity\Invitation::class, 'i', 'WITH', 'i.bet = b AND i.invitee = :user AND i.revokedAt IS NULL')
            ->where('b.bookie = :user OR i.id IS NOT NULL')
            ->setParameter('user', $user)
            ->orderBy('b.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()->getResult();
    }
}
