<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;

/**
 * Transport du lien de vérification. Aucun fournisseur d'email n'est autorisé par la spec :
 * le choix du transport réel est une décision humaine à prendre avant la mise en production.
 */
interface EmailVerificationSender
{
    public function send(User $user, string $rawToken): void;
}
