<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security;

use App\Service\AuditLogger;
use App\Tests\Functional\ApiTestCase;

/**
 * Un test par abuse story du backlog. Chaque test échoue si la protection correspondante est retirée.
 */
final class BettingAbuseStoriesTest extends ApiTestCase
{
    // Abuse story 1 : un parieur modifie ou supprime un pari.
    public function testBettorCannotModifySomeoneElsesBet(): void
    {
        $bookie = $this->account(bookie: true);
        $bettor = $this->account();
        $betId = $this->createBet($bookie);
        $this->invite($bookie, $betId, $bettor);

        $this->call('PATCH', "/api/bets/$betId", ['min_stake_cents' => 100, 'max_stake_cents' => 100000, 'odds_hundredths' => 9900], $bettor);
        self::assertResponseStatusCodeSame(404);
        $this->call('POST', "/api/bets/$betId/result", ['outcome' => true], $bettor);
        self::assertResponseStatusCodeSame(404);
    }

    public function testNonBookieCannotCreateBet(): void
    {
        $user = $this->account();
        $this->call('POST', '/api/bets', ['title' => 'x'], $user);
        self::assertResponseStatusCodeSame(403);
    }

    // Abuse story 2 : faux comptes.
    public function testUnverifiedAccountCannotWager(): void
    {
        $bookie = $this->account(bookie: true);
        $bettor = $this->account(verified: false);
        $betId = $this->createBet($bookie);
        $this->invite($bookie, $betId, $bettor);
        $this->deposit($bettor, 1000);

        $this->call('POST', "/api/bets/$betId/wagers", ['prediction' => true, 'stake_cents' => 500], $bettor);
        self::assertResponseStatusCodeSame(403);
    }

    public function testVerificationTokenIsSingleUse(): void
    {
        $email = bin2hex(random_bytes(6)) . '@example.test';
        $this->call('POST', '/api/register', ['email' => $email, 'password' => 'password-long-enough']);
        $token = static::getContainer()->get(\App\Service\InMemoryEmailVerificationSender::class)->lastTokenFor($email);

        $this->call('POST', '/api/verify-email', ['token' => $token]);
        self::assertResponseIsSuccessful();
        $this->call('POST', '/api/verify-email', ['token' => $token]);
        self::assertResponseStatusCodeSame(400);
    }

    public function testRegistrationIsRateLimited(): void
    {
        for ($i = 0; $i < 5; ++$i) {
            $this->call('POST', '/api/register', ['email' => bin2hex(random_bytes(6)) . '@example.test', 'password' => 'password-long-enough']);
            self::assertResponseStatusCodeSame(201);
        }
        $this->call('POST', '/api/register', ['email' => bin2hex(random_bytes(6)) . '@example.test', 'password' => 'password-long-enough']);
        self::assertResponseStatusCodeSame(429);
    }

    public function testClientCannotGrantItselfAdminRole(): void
    {
        $email = bin2hex(random_bytes(6)) . '@example.test';
        $this->call('POST', '/api/register', ['email' => $email, 'password' => 'password-long-enough', 'roles' => ['ROLE_ADMIN'], 'bookie' => 'yes']);
        $user = static::getContainer()->get(\App\Repository\UserRepository::class)->findByEmail($email);
        self::assertSame(['ROLE_PARIEUR'], $user->getRoles());
    }

    public function testUninvitedUserCannotSeeOrWager(): void
    {
        $bookie = $this->account(bookie: true);
        $stranger = $this->account();
        $betId = $this->createBet($bookie);
        $this->deposit($stranger, 1000);

        $this->call('GET', "/api/bets/$betId", null, $stranger);
        self::assertResponseStatusCodeSame(404);
        $this->call('POST', "/api/bets/$betId/wagers", ['prediction' => true, 'stake_cents' => 500], $stranger);
        self::assertResponseStatusCodeSame(404);
    }

    public function testRevokedInvitationCannotWager(): void
    {
        $bookie = $this->account(bookie: true);
        $bettor = $this->account();
        $betId = $this->createBet($bookie);
        $invitationId = $this->invite($bookie, $betId, $bettor);
        $this->deposit($bettor, 1000);

        $this->call('DELETE', "/api/bets/$betId/invitations/$invitationId", null, $bookie);
        self::assertResponseStatusCodeSame(204);
        $this->call('POST', "/api/bets/$betId/wagers", ['prediction' => true, 'stake_cents' => 500], $bettor);
        self::assertResponseStatusCodeSame(404);
    }

    // Abuse story 3 : modifier les mises ou la cotation.
    public function testOddsAreLockedAfterFirstWager(): void
    {
        [$bookie, $bettor, $betId] = $this->betWithInvitedFundedBettor();
        $this->call('POST', "/api/bets/$betId/wagers", ['prediction' => true, 'stake_cents' => 500], $bettor);
        self::assertResponseStatusCodeSame(201);

        $this->call('PATCH', "/api/bets/$betId", ['min_stake_cents' => 100, 'max_stake_cents' => 5000, 'odds_hundredths' => 900], $bookie);
        self::assertResponseStatusCodeSame(409);
    }

    public function testStakeOutsideBoundsIsRejected(): void
    {
        [, $bettor, $betId] = $this->betWithInvitedFundedBettor();
        $this->call('POST', "/api/bets/$betId/wagers", ['prediction' => true, 'stake_cents' => 50], $bettor);
        self::assertResponseStatusCodeSame(422);
    }

    public function testBookieCannotBetOnOwnBet(): void
    {
        $bookie = $this->account(bookie: true);
        $betId = $this->createBet($bookie);
        $this->deposit($bookie, 1000);
        $this->call('POST', "/api/bets/$betId/wagers", ['prediction' => true, 'stake_cents' => 500], $bookie);
        self::assertResponseStatusCodeSame(403);
    }

    // Abuse story 4 : miser / modifier sa mise après la clôture.
    public function testWagerAfterClosingIsRejected(): void
    {
        [$bookie, $bettor, $betId] = $this->betWithInvitedFundedBettor();
        $this->call('POST', "/api/bets/$betId/close", null, $bookie);
        self::assertResponseIsSuccessful();

        $this->call('POST', "/api/bets/$betId/wagers", ['prediction' => true, 'stake_cents' => 500], $bettor);
        self::assertResponseStatusCodeSame(409);
    }

    public function testWagerCannotBeModifiedOrPlacedTwice(): void
    {
        [, $bettor, $betId] = $this->betWithInvitedFundedBettor();
        $this->call('POST', "/api/bets/$betId/wagers", ['prediction' => true, 'stake_cents' => 500], $bettor);
        $wagerId = $this->json()['id'];

        $this->call('PATCH', "/api/bets/$betId/wagers/$wagerId", ['stake_cents' => 5000], $bettor);
        self::assertContains($this->client->getResponse()->getStatusCode(), [404, 405]);
        $this->call('POST', "/api/bets/$betId/wagers", ['prediction' => false, 'stake_cents' => 500], $bettor);
        self::assertResponseStatusCodeSame(409);
    }

    // Abuse story 6 : accéder à l'argent séquestré / double dépense.
    public function testCannotWagerMoreThanBalance(): void
    {
        $bookie = $this->account(bookie: true);
        $bettor = $this->account();
        $betId = $this->createBet($bookie);
        $this->invite($bookie, $betId, $bettor);
        $this->deposit($bettor, 300);

        $this->call('POST', "/api/bets/$betId/wagers", ['prediction' => true, 'stake_cents' => 500], $bettor);
        self::assertResponseStatusCodeSame(422);
    }

    public function testUnsignedWebhookIsRejected(): void
    {
        $user = $this->account();
        $payload = json_encode(['type' => 'payment_intent.succeeded', 'data' => ['object' => ['id' => 'pi_fake', 'amount_received' => 100000, 'metadata' => ['user_id' => $user['id']]]]]);
        $this->client->request('POST', '/api/webhooks/payment', server: ['HTTP_STRIPE_SIGNATURE' => 't=' . time() . ',v1=deadbeef'], content: $payload);
        self::assertResponseStatusCodeSame(400);

        $this->call('GET', '/api/me/transactions', null, $user);
        self::assertSame(0, $this->json()['balance_cents']);
    }

    // Abuse story 7 : fausser le résultat.
    public function testResultCannotBeValidatedBeforeClosingNorTwice(): void
    {
        [$bookie, , $betId] = $this->betWithInvitedFundedBettor();
        $this->call('POST', "/api/bets/$betId/result", ['outcome' => true], $bookie);
        self::assertResponseStatusCodeSame(409);

        $this->call('POST', "/api/bets/$betId/close", null, $bookie);
        $this->call('POST', "/api/bets/$betId/result", ['outcome' => true], $bookie);
        self::assertResponseIsSuccessful();
        $this->call('POST', "/api/bets/$betId/result", ['outcome' => false], $bookie);
        self::assertResponseStatusCodeSame(409);
    }

    public function testAnotherBookieCannotValidateResult(): void
    {
        [, , $betId] = $this->betWithInvitedFundedBettor();
        $otherBookie = $this->account(bookie: true);
        $this->call('POST', "/api/bets/$betId/result", ['outcome' => true], $otherBookie);
        self::assertResponseStatusCodeSame(404);
    }

    // Abuse story 8 : détourner les gains. Le pot est intégralement et exactement redistribué.
    public function testPayoutGoesToWinnersAndPotIsConserved(): void
    {
        $bookie = $this->account(bookie: true);
        $winner = $this->account();
        $loser = $this->account();
        $betId = $this->createBet($bookie);
        foreach ([$winner, $loser] as $bettor) {
            $this->invite($bookie, $betId, $bettor);
            $this->deposit($bettor, 1000);
        }
        $this->call('POST', "/api/bets/$betId/wagers", ['prediction' => true, 'stake_cents' => 1000, 'beneficiary' => $loser['id']], $winner);
        $this->call('POST', "/api/bets/$betId/wagers", ['prediction' => false, 'stake_cents' => 1000], $loser);
        $this->call('POST', "/api/bets/$betId/close", null, $bookie);
        $this->call('POST', "/api/bets/$betId/result", ['outcome' => true], $bookie);
        self::assertResponseIsSuccessful();

        // Pot 2000 : plateforme 5 % = 100, Bookie 5 % = 100, gagnant = 1800.
        $this->call('GET', '/api/me/transactions', null, $winner);
        self::assertSame(1800, $this->json()['balance_cents']);
        $this->call('GET', '/api/me/transactions', null, $loser);
        self::assertSame(0, $this->json()['balance_cents']);
        $this->call('GET', '/api/me/transactions', null, $bookie);
        self::assertSame(100, $this->json()['balance_cents']);
    }

    // Abuse story 9 : accéder à l'historique d'un autre utilisateur.
    public function testHistoryOnlyShowsOwnData(): void
    {
        [, $bettor, $betId] = $this->betWithInvitedFundedBettor();
        $this->call('POST', "/api/bets/$betId/wagers", ['prediction' => true, 'stake_cents' => 500], $bettor);
        $spy = $this->account();

        $this->call('GET', '/api/me/wagers?user_id=' . $bettor['id'], null, $spy);
        self::assertResponseIsSuccessful();
        self::assertSame([], $this->json()['items']);
    }

    // Répudiation : la chaîne du journal d'audit est intègre et l'UPDATE est refusé par la base.
    public function testAuditLogIsChainedAndAppendOnly(): void
    {
        $this->betWithInvitedFundedBettor();
        self::assertTrue(static::getContainer()->get(AuditLogger::class)->verifyChain());

        $this->expectException(\Doctrine\DBAL\Exception::class);
        static::getContainer()->get('doctrine.dbal.default_connection')->executeStatement("UPDATE audit_logs SET action = 'forged'");
    }

    public function testSecurityHeadersArePresent(): void
    {
        $this->call('GET', '/api/me/wagers');
        $headers = $this->client->getResponse()->headers;
        self::assertStringContainsString('max-age=', (string) $headers->get('Strict-Transport-Security'));
        self::assertStringNotContainsString('unsafe-inline', (string) $headers->get('Content-Security-Policy'));
        self::assertSame('nosniff', $headers->get('X-Content-Type-Options'));
    }

    /** @return array{0:array,1:array,2:string} */
    private function betWithInvitedFundedBettor(): array
    {
        $bookie = $this->account(bookie: true);
        $bettor = $this->account();
        $betId = $this->createBet($bookie);
        $this->invite($bookie, $betId, $bettor);
        $this->deposit($bettor, 1000);

        return [$bookie, $bettor, $betId];
    }
}
