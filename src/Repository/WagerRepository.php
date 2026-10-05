<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Wager;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Wager> */
final class WagerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Wager::class);
    }

    /** @return list<Wager> */
    public function findForBet(\App\Entity\Bet $bet): array
    {
        return $this->findBy(['bet' => $bet], ['createdAt' => 'ASC']);
    }

    /** @return list<Wager> Historique : uniquement les mises de l'utilisateur connecté (anti-IDOR). */
    public function findForBettor(\App\Entity\User $user, int $page, int $perPage): array
    {
        return $this->findBy(['bettor' => $user], ['createdAt' => 'DESC'], $perPage, ($page - 1) * $perPage);
    }
}
