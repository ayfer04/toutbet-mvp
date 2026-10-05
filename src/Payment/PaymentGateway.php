<?php

declare(strict_types=1);

namespace App\Payment;

use App\Entity\User;

/**
 * Abstraction du prestataire de paiement (Stripe en mode test), comme exigé par la spec.
 * STRIDE: Information Disclosure — l'application ne voit jamais de données de carte :
 * la saisie se fait côté Stripe, seul un identifiant de paiement revient.
 */
interface PaymentGateway
{
    /** @return array{payment_id:string, client_secret:string} */
    public function createDeposit(User $user, int $amountCents): array;
}
