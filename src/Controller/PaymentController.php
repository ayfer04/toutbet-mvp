<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Transaction;
use App\Entity\User;
use App\Http\Json;
use App\Payment\PaymentGateway;
use App\Payment\WebhookSignatureVerifier;
use App\Repository\TransactionRepository;
use App\Repository\UserRepository;
use App\Service\AuditLogger;
use App\Service\Uuid;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class PaymentController
{
    #[Route('/api/payments/deposits', name: 'payment_deposit', methods: ['POST'])]
    public function deposit(Request $request, #[CurrentUser] User $user, PaymentGateway $gateway): JsonResponse
    {
        $data = Json::body($request);
        $amount = $data['amount_cents'] ?? null;
        if (!is_int($amount) || $amount < 100 || $amount > 100000) {
            return Json::error('Invalid amount.', 400);
        }

        // Le solde n'est PAS crédité ici : uniquement à la réception du webhook signé du prestataire.
        return new JsonResponse($gateway->createDeposit($user, $amount), 201);
    }

    #[Route('/api/webhooks/payment', name: 'payment_webhook', methods: ['POST'])]
    public function webhook(
        Request $request,
        WebhookSignatureVerifier $verifier,
        UserRepository $users,
        TransactionRepository $ledger,
        EntityManagerInterface $entityManager,
        AuditLogger $audit,
    ): JsonResponse {
        $payload = $request->getContent();
        // STRIDE: Spoofing / Tampering — signature obligatoire, sinon rejet sans traitement.
        if (!$verifier->isValid($payload, (string) $request->headers->get('Stripe-Signature'))) {
            return Json::error('Invalid signature.', 400);
        }

        try {
            $event = json_decode($payload, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return Json::error('Invalid payload.', 400);
        }
        $object = $event['data']['object'] ?? null;
        if (($event['type'] ?? null) !== 'payment_intent.succeeded' || !is_array($object)) {
            return new JsonResponse(['ignored' => true]);
        }
        $paymentId = $object['id'] ?? null;
        $amount = $object['amount_received'] ?? null;
        $userId = $object['metadata']['user_id'] ?? null;
        if (!is_string($paymentId) || !is_int($amount) || $amount <= 0 || !is_string($userId) || !Uuid::isValid($userId)) {
            return Json::error('Invalid payload.', 400);
        }
        $user = $users->find($userId);
        if ($user === null) {
            return Json::error('Invalid payload.', 400);
        }

        // Idempotence : un webhook rejoué ne crédite jamais deux fois.
        $key = 'deposit:' . $paymentId;
        if (!$ledger->existsWithKey($key)) {
            $entityManager->wrapInTransaction(function () use ($entityManager, $user, $amount, $key, $paymentId, $audit): void {
                $entityManager->persist(new Transaction(Uuid::v4(), $user, null, Transaction::DEPOSIT, $amount, $key, $paymentId));
                $audit->log(null, 'payment.deposit_received', ['user_id' => $user->getId(), 'amount_cents' => $amount, 'payment_id' => $paymentId]);
            });
        }

        return new JsonResponse(['received' => true]);
    }
}
