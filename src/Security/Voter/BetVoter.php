<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Bet;
use App\Entity\User;
use App\Repository\InvitationRepository;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * STRIDE: Elevation of Privilege / Information Disclosure — contrôle d'accès côté serveur, refus par défaut.
 *
 * @extends Voter<string, Bet>
 */
final class BetVoter extends Voter
{
    public const VIEW = 'BET_VIEW';
    public const MANAGE = 'BET_MANAGE';

    public function __construct(private readonly InvitationRepository $invitations) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::MANAGE], true) && $subject instanceof Bet;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?\Symfony\Component\Security\Core\Authorization\Voter\Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        return match ($attribute) {
            // Gérer (inviter, modifier, clôturer, valider) : uniquement le Bookie propriétaire.
            self::MANAGE => $user->isBookie() && $subject->isOwnedBy($user),
            // Voir : le Bookie propriétaire ou un invité dont l'invitation n'est pas révoquée.
            self::VIEW => $subject->isOwnedBy($user) || $this->invitations->findActive($subject, $user) !== null,
            default => false,
        };
    }
}
