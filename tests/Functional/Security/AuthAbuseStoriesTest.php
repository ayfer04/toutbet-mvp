<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AuthAbuseStoriesTest extends WebTestCase
{
    public function testInvalidCredentialsCannotAuthenticate(): void
    {
        $client = static::createClient([], ['REMOTE_ADDR' => '10.9.' . random_int(0, 255) . '.' . random_int(1, 254)]);
        $client->jsonRequest('POST', '/api/login', [
            'email' => 'victim@example.test',
            'password' => 'wrong',
        ]);

        // STRIDE: Spoofing — an attacker cannot authenticate with invalid credentials.
        self::assertResponseStatusCodeSame(401);
    }

    public function testTamperedBearerTokenCannotAuthenticate(): void
    {
        $client = static::createClient([], ['REMOTE_ADDR' => '10.9.' . random_int(0, 255) . '.' . random_int(1, 254)]);
        $client->request('GET', '/api/me/wagers', server: [
            'HTTP_AUTHORIZATION' => 'Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.invalid.signature',
        ]);

        // STRIDE: Spoofing / Tampering — a forged token is rejected before application access.
        self::assertResponseStatusCodeSame(401);
    }

    public function testLoginIsRateLimited(): void
    {
        $client = static::createClient([], ['REMOTE_ADDR' => '10.9.' . random_int(0, 255) . '.' . random_int(1, 254)]);
        for ($i = 0; $i < 5; ++$i) {
            $client->jsonRequest('POST', '/api/login', [
                'email' => 'nobody@example.test',
                'password' => 'wrong',
            ]);
            self::assertResponseStatusCodeSame(401);
        }

        $client->jsonRequest('POST', '/api/login', [
            'email' => 'nobody@example.test',
            'password' => 'wrong',
        ]);

        // STRIDE: Denial of Service — excessive login attempts are blocked.
        self::assertResponseStatusCodeSame(429);
    }
}
