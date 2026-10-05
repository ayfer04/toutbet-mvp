<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Transaction;
use App\Entity\User;
use App\Entity\Wager;
use App\Http\Json;
use App\Repository\TransactionRepository;
use App\Repository\WagerRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * STRIDE: Information Disclosure — l'historique est toujours celui de l'utilisateur connecté :
 * aucun identifiant d'utilisateur n'est accepté en paramètre, donc aucun IDOR possible.
 */
#[Route('/api/me')]
final class HistoryController
{
    #[Route('/wagers', name: 'me_wagers', methods: ['GET'])]
    public function wagers(Request $request, #[CurrentUser] User $user, WagerRepository $wagers): JsonResponse
    {
        [$page, $perPage] = Json::pagination($request);

        return new JsonResponse(['page' => $page, 'items' => array_map(static fn (Wager $w) => $w->toArray(), $wagers->findForBettor($user, $page, $perPage))]);
    }

    #[Route('/transactions', name: 'me_transactions', methods: ['GET'])]
    public function transactions(Request $request, #[CurrentUser] User $user, TransactionRepository $ledger): JsonResponse
    {
        [$page, $perPage] = Json::pagination($request);

        return new JsonResponse([
            'page' => $page,
            'balance_cents' => $ledger->balanceOf($user),
            'items' => array_map(static fn (Transaction $t) => $t->toArray(), $ledger->findForUser($user, $page, $perPage)),
        ]);
    }
}
