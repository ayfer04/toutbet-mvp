<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $id;

    #[ORM\Column(length: 180, unique: true)]
    private string $email;

    #[ORM\Column]
    private string $passwordHash;

    #[ORM\Column(type: 'json')]
    private array $roles = ['ROLE_PARIEUR'];

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $refreshTokenHash = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $refreshTokenExpiresAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $emailVerifiedAt = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $emailVerificationTokenHash = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $emailVerificationExpiresAt = null;

    public function __construct(string $id, string $email, string $passwordHash)
    {
        $this->id = $id;
        $this->email = strtolower(trim($email));
        $this->passwordHash = $passwordHash;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): string { return $this->id; }
    public function getUserIdentifier(): string { return $this->email; }
    public function getEmail(): string { return $this->email; }
    public function getPassword(): string { return $this->passwordHash; }
    public function setPasswordHash(string $passwordHash): void { $this->passwordHash = $passwordHash; }
    public function getRoles(): array { return array_values(array_unique(array_merge(['ROLE_PARIEUR'], $this->roles))); }
    public function setRoles(array $roles): void { $this->roles = $roles; }
    public function eraseCredentials(): void {}
    public function setRefreshToken(string $hash, \DateTimeImmutable $expiresAt): void
    {
        $this->refreshTokenHash = $hash;
        $this->refreshTokenExpiresAt = $expiresAt;
    }
    public function clearRefreshToken(): void
    {
        $this->refreshTokenHash = null;
        $this->refreshTokenExpiresAt = null;
    }
    public function getRefreshTokenHash(): ?string { return $this->refreshTokenHash; }
    public function getRefreshTokenExpiresAt(): ?\DateTimeImmutable { return $this->refreshTokenExpiresAt; }

    public function isBookie(): bool { return in_array('ROLE_BOOKIE', $this->getRoles(), true); }

    // STRIDE: Spoofing (faux comptes) — un compte non vérifié ne peut pas miser.
    public function isEmailVerified(): bool { return $this->emailVerifiedAt !== null; }

    public function setEmailVerificationToken(string $hash, \DateTimeImmutable $expiresAt): void
    {
        $this->emailVerificationTokenHash = $hash;
        $this->emailVerificationExpiresAt = $expiresAt;
    }

    public function getEmailVerificationTokenHash(): ?string { return $this->emailVerificationTokenHash; }
    public function getEmailVerificationExpiresAt(): ?\DateTimeImmutable { return $this->emailVerificationExpiresAt; }

    public function markEmailVerified(): void
    {
        $this->emailVerifiedAt = new \DateTimeImmutable();
        // Usage unique : le jeton est détruit dès qu'il a servi.
        $this->emailVerificationTokenHash = null;
        $this->emailVerificationExpiresAt = null;
    }
}
