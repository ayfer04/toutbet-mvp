<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Http\Json;
use App\Service\Uuid;
use App\Service\WagerRejected;
use App\Service\WagerService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class WagerController
{
    private const STATUS = [
        'not_found' => 404,
        'bookie_cannot_bet' => 403,
        'email_not_verified' => 403,
        'bet_closed' => 409,
        'already_wagered' => 409,
        'stake_out_of_bounds' => 422,
        'insufficient_funds' => 422,
    ];

    #[Route('/api/bets/{id}/wagers', name: 'wager_place', methods: ['POST'])]
    public function place(string $id, Request $request, #[CurrentUser] User $user, WagerService $wagers, RateLimiterFactory $betActionLimiter): JsonResponse
    {
        // STRIDE: Denial of Service — anti-bots : mises limitées par utilisateur.
        if (!$betActionLimiter->create($user->getId())->consume(1)->isAccepted()) {
            return Json::error('Too many requests.', 429);
        }
        $data = Json::body($request);
        $prediction = $data['prediction'] ?? null;
        $stake = $data['stake_cents'] ?? null;
        if (!Uuid::isValid($id)) {
            return Json::error('Not found.', 404);
        }
        if (!is_bool($prediction) || !is_int($stake)) {
            return Json::error('Invalid wager.', 400);
        }

        try {
            $wager = $wagers->place($user, $id, $prediction, $stake);
        } catch (WagerRejected $e) {
            return Json::error($e->reason === 'not_found' ? 'Not found.' : 'Wager rejected: ' . $e->reason, self::STATUS[$e->reason] ?? 400);
        }

        return new JsonResponse($wager->toArray(), 201);
    }
}
