<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<User> */
final class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function findByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => strtolower(trim($email))]);
    }

    public function findByRefreshTokenHash(string $hash): ?User
    {
        return $this->findOneBy(['refreshTokenHash' => $hash]);
    }

    public function findByEmailVerificationTokenHash(string $hash): ?User
    {
        return $this->findOneBy(['emailVerificationTokenHash' => $hash]);
    }
}
