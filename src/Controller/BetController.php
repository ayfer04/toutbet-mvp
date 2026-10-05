<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Bet;
use App\Entity\Invitation;
use App\Entity\User;
use App\Http\Json;
use App\Repository\BetRepository;
use App\Repository\InvitationRepository;
use App\Repository\UserRepository;
use App\Security\Voter\BetVoter;
use App\Service\AuditLogger;
use App\Service\SettlementService;
use App\Service\Uuid;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/bets')]
final class BetController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly BetRepository $bets,
        private readonly AuditLogger $audit,
        private readonly RateLimiterFactory $betActionLimiter,
    ) {}

    #[Route('', name: 'bet_create', methods: ['POST'])]
    #[IsGranted('ROLE_BOOKIE')]
    public function create(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        if (!$this->betActionLimiter->create($user->getId())->consume(1)->isAccepted()) {
            return Json::error('Too many requests.', 429);
        }
        $data = Json::body($request);
        $terms = $data === null ? null : $this->readTerms($data);
        $title = is_string($data['title'] ?? null) ? trim($data['title']) : '';
        $commission = $data['bookie_commission_bps'] ?? 0;
        $closesAt = is_string($data['closes_at'] ?? null) ? \DateTimeImmutable::createFromFormat(\DATE_ATOM, $data['closes_at']) : false;

        // Validation stricte côté serveur : types, bornes, dates (STRIDE: Tampering).
        if ($terms === null || $title === '' || mb_strlen($title) > 140 || !is_int($commission) || $commission < 0 || $commission > 1000
            || $closesAt === false || $closesAt <= new \DateTimeImmutable()) {
            return Json::error('Invalid bet.', 400);
        }

        $bet = new Bet(Uuid::v4(), $user, $title, $terms[0], $terms[1], $terms[2], $commission, $closesAt);
        $this->entityManager->wrapInTransaction(function () use ($bet, $user): void {
            $this->entityManager->persist($bet);
            $this->audit->log($user, 'bet.created', ['bet_id' => $bet->getId()] + $bet->toArray());
        });

        return new JsonResponse($bet->toArray(), 201);
    }

    #[Route('', name: 'bet_list', methods: ['GET'])]
    public function list(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        [$page, $perPage] = Json::pagination($request);

        return new JsonResponse([
            'page' => $page,
            'items' => array_map(static fn (Bet $b) => $b->toArray(), $this->bets->findVisibleTo($user, $page, $perPage)),
        ]);
    }

    #[Route('/{id}', name: 'bet_show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $bet = $this->findOr404($id);
        // Un utilisateur non autorisé reçoit 404 et non 403 : on ne confirme pas l'existence du pari (anti-IDOR).
        if ($bet === null || !$this->isGranted(BetVoter::VIEW, $bet)) {
            return Json::error('Not found.', 404);
        }

        return new JsonResponse($bet->toArray());
    }

    #[Route('/{id}', name: 'bet_update_terms', methods: ['PATCH'])]
    public function updateTerms(string $id, Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $bet = $this->findOr404($id);
        if ($bet === null || !$this->isGranted(BetVoter::MANAGE, $bet)) {
            return Json::error('Not found.', 404);
        }
        $data = Json::body($request);
        $terms = $data === null ? null : $this->readTerms($data);
        if ($terms === null) {
            return Json::error('Invalid terms.', 400);
        }

        try {
            $this->entityManager->wrapInTransaction(function () use ($bet, $terms, $user): void {
                $this->entityManager->lock($bet, \Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE);
                $this->entityManager->refresh($bet);
                $bet->updateTerms(...$terms);
                $this->audit->log($user, 'bet.terms_updated', ['bet_id' => $bet->getId(), 'min' => $terms[0], 'max' => $terms[1], 'odds' => $terms[2]]);
            });
        } catch (\DomainException) {
            // STRIDE: Tampering — cotation et plafonds figés après la 1re mise.
            return Json::error('Terms are locked once a wager exists.', 409);
        }

        return new JsonResponse($bet->toArray());
    }

    #[Route('/{id}/close', name: 'bet_close', methods: ['POST'])]
    public function close(string $id, #[CurrentUser] User $user): JsonResponse
    {
        $bet = $this->findOr404($id);
        if ($bet === null || !$this->isGranted(BetVoter::MANAGE, $bet)) {
            return Json::error('Not found.', 404);
        }
        try {
            $this->entityManager->wrapInTransaction(function () use ($bet, $user): void {
                $this->entityManager->lock($bet, \Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE);
                $bet->close();
                $this->audit->log($user, 'bet.closed', ['bet_id' => $bet->getId()]);
            });
        } catch (\DomainException) {
            return Json::error('Bet is not open.', 409);
        }

        return new JsonResponse($bet->toArray());
    }

    #[Route('/{id}/result', name: 'bet_result', methods: ['POST'])]
    public function validateResult(string $id, Request $request, #[CurrentUser] User $user, SettlementService $settlement): JsonResponse
    {
        $bet = $this->findOr404($id);
        if ($bet === null || !$this->isGranted(BetVoter::MANAGE, $bet)) {
            return Json::error('Not found.', 404);
        }
        $data = Json::body($request);
        if (!is_bool($data['outcome'] ?? null)) {
            return Json::error('Invalid outcome.', 400);
        }
        try {
            $bet = $settlement->settle($user, $id, $data['outcome']);
        } catch (\DomainException) {
            // Déjà validé, ou pari pas encore clos (STRIDE: Tampering).
            return Json::error('Result cannot be validated.', 409);
        }

        return new JsonResponse($bet->toArray());
    }

    #[Route('/{id}/invitations', name: 'bet_invite', methods: ['POST'])]
    public function invite(string $id, Request $request, #[CurrentUser] User $user, UserRepository $users, InvitationRepository $invitations): JsonResponse
    {
        $bet = $this->findOr404($id);
        if ($bet === null || !$this->isGranted(BetVoter::MANAGE, $bet)) {
            return Json::error('Not found.', 404);
        }
        $data = Json::body($request);
        $invitee = is_string($data['email'] ?? null) ? $users->findByEmail($data['email']) : null;
        // Réponse identique si l'email n'existe pas : pas d'énumération des comptes (STRIDE: Information Disclosure).
        if ($invitee === null || $invitee->getId() === $user->getId()) {
            return Json::error('Invitation could not be created.', 400);
        }
        if ($invitations->findOneBy(['bet' => $bet, 'invitee' => $invitee]) !== null) {
            return Json::error('Invitation could not be created.', 409);
        }

        $invitation = new Invitation(Uuid::v4(), $bet, $invitee);
        $this->entityManager->wrapInTransaction(function () use ($invitation, $user, $bet, $invitee): void {
            $this->entityManager->persist($invitation);
            $this->audit->log($user, 'invitation.created', ['bet_id' => $bet->getId(), 'invitee_id' => $invitee->getId()]);
        });

        return new JsonResponse(['id' => $invitation->getId()], 201);
    }

    #[Route('/{id}/invitations/{invitationId}', name: 'bet_revoke_invitation', methods: ['DELETE'])]
    public function revoke(string $id, string $invitationId, #[CurrentUser] User $user, InvitationRepository $invitations): JsonResponse
    {
        $bet = $this->findOr404($id);
        if ($bet === null || !$this->isGranted(BetVoter::MANAGE, $bet) || !Uuid::isValid($invitationId)) {
            return Json::error('Not found.', 404);
        }
        $invitation = $invitations->find($invitationId);
        // L'invitation doit appartenir à CE pari (anti-IDOR croisé entre paris).
        if ($invitation === null || $invitation->getBet()->getId() !== $bet->getId()) {
            return Json::error('Not found.', 404);
        }
        $this->entityManager->wrapInTransaction(function () use ($invitation, $user, $bet): void {
            $invitation->revoke();
            $this->audit->log($user, 'invitation.revoked', ['bet_id' => $bet->getId(), 'invitation_id' => $invitation->getId()]);
        });

        return new JsonResponse(null, 204);
    }

    private function findOr404(string $id): ?Bet
    {
        return Uuid::isValid($id) ? $this->bets->find($id) : null;
    }

    /**
     * @param array<string, mixed> $data
     * @return array{0:int,1:int,2:int}|null
     */
    private function readTerms(array $data): ?array
    {
        $min = $data['min_stake_cents'] ?? null;
        $max = $data['max_stake_cents'] ?? null;
        $odds = $data['odds_hundredths'] ?? null;
        // Entiers uniquement (centimes / centièmes) ; bornes métier raisonnables.
        if (!is_int($min) || !is_int($max) || !is_int($odds) || $min < 100 || $max < $min || $max > 100000 || $odds < 101 || $odds > 100000) {
            return null;
        }

        return [$min, $max, $odds];
    }
}
