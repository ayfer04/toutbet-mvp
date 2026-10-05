<?php

declare(strict_types=1);

namespace App\Payment;

use App\Entity\User;

/**
 * Implémentation de développement/test. À remplacer par un adaptateur Stripe (mode test)
 * implémentant la même interface ; aucun autre code n'a besoin de changer.
 */
final class FakePaymentGateway implements PaymentGateway
{
    public function createDeposit(User $user, int $amountCents): array
    {
        $id = 'pi_test_' . bin2hex(random_bytes(12));

        return ['payment_id' => $id, 'client_secret' => $id . '_secret_' . bin2hex(random_bytes(12))];
    }
}
