<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Invitation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Invitation> */
final class InvitationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Invitation::class);
    }

    public function findActive(\App\Entity\Bet $bet, \App\Entity\User $invitee): ?Invitation
    {
        return $this->createQueryBuilder('i')
            ->where('i.bet = :bet AND i.invitee = :invitee AND i.revokedAt IS NULL')
            ->setParameter('bet', $bet)->setParameter('invitee', $invitee)
            ->getQuery()->getOneOrNullResult();
    }
}
