<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Payment\WebhookSignatureVerifier;
use App\Service\InMemoryEmailVerificationSender;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class ApiTestCase extends WebTestCase
{
    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        // Chaque test a son propre REMOTE_ADDR : les rate limiters par IP ne se gênent pas entre tests.
        $this->client->setServerParameter('REMOTE_ADDR', '10.' . random_int(0, 255) . '.' . random_int(0, 255) . '.' . random_int(1, 254));
    }

    /** Crée un compte, vérifie (ou non) son email, et renvoie son jeton d'accès. */
    protected function account(bool $bookie = false, bool $verified = true): array
    {
        $email = bin2hex(random_bytes(6)) . '@example.test';
        $this->call('POST', '/api/register', ['email' => $email, 'password' => 'correct horse battery staple', 'bookie' => $bookie]);
        self::assertResponseStatusCodeSame(201);
        $id = $this->json()['id'];

        if ($verified) {
            /** @var InMemoryEmailVerificationSender $sender */
            $sender = static::getContainer()->get(InMemoryEmailVerificationSender::class);
            $this->call('POST', '/api/verify-email', ['token' => $sender->lastTokenFor($email)]);
            self::assertResponseIsSuccessful();
        }

        $this->call('POST', '/api/login', ['email' => $email, 'password' => 'correct horse battery staple']);

        return ['id' => $id, 'email' => $email, 'token' => $this->json()['access_token']];
    }

    protected function call(string $method, string $uri, ?array $body = null, ?array $as = null): void
    {
        $server = $as === null ? [] : ['HTTP_AUTHORIZATION' => 'Bearer ' . $as['token']];
        $this->client->request($method, $uri, server: $server + ['CONTENT_TYPE' => 'application/json'], content: $body === null ? null : json_encode($body, JSON_THROW_ON_ERROR));
    }

    protected function json(): array
    {
        return json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR) ?? [];
    }

    protected function createBet(array $bookie, string $closesIn = '+1 day', array $overrides = []): string
    {
        $this->call('POST', '/api/bets', $overrides + [
            'title' => 'Matis va-t-il réussir son créneau ?',
            'min_stake_cents' => 100,
            'max_stake_cents' => 5000,
            'odds_hundredths' => 200,
            'bookie_commission_bps' => 500,
            'closes_at' => (new \DateTimeImmutable($closesIn))->format(\DATE_ATOM),
        ], $bookie);
        self::assertResponseStatusCodeSame(201);

        return $this->json()['id'];
    }

    protected function invite(array $bookie, string $betId, array $bettor): string
    {
        $this->call('POST', "/api/bets/$betId/invitations", ['email' => $bettor['email']], $bookie);
        self::assertResponseStatusCodeSame(201);

        return $this->json()['id'];
    }

    /** Simule le webhook signé du prestataire de paiement pour créditer un compte. */
    protected function deposit(array $user, int $amountCents): void
    {
        $payload = json_encode(['type' => 'payment_intent.succeeded', 'data' => ['object' => [
            'id' => 'pi_test_' . bin2hex(random_bytes(6)), 'amount_received' => $amountCents, 'metadata' => ['user_id' => $user['id']],
        ]]], JSON_THROW_ON_ERROR);
        /** @var WebhookSignatureVerifier $verifier */
        $verifier = static::getContainer()->get(WebhookSignatureVerifier::class);
        $this->client->request('POST', '/api/webhooks/payment', server: ['HTTP_STRIPE_SIGNATURE' => $verifier->sign($payload, time()), 'CONTENT_TYPE' => 'application/json'], content: $payload);
        self::assertResponseIsSuccessful();
    }
}
