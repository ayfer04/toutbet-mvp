<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AuthControllerTest extends WebTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        self::ensureKernelShutdown();
    }

    public function testUserCanRegisterAndPasswordIsNotReturned(): void
    {
        $client = static::createClient([], ['REMOTE_ADDR' => '10.9.' . random_int(0, 255) . '.' . random_int(1, 254)]);
        $client->jsonRequest('POST', '/api/register', [
            'email' => 'alice@example.test',
            'password' => 'correct horse battery staple',
        ]);

        self::assertResponseStatusCodeSame(201);
        $data = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('id', $data);
        self::assertArrayNotHasKey('password', $data);
        self::assertArrayNotHasKey('passwordHash', $data);
    }

    public function testDuplicateAccountIsRejected(): void
    {
        $client = static::createClient([], ['REMOTE_ADDR' => '10.9.' . random_int(0, 255) . '.' . random_int(1, 254)]);
        $client->jsonRequest('POST', '/api/register', ['email' => 'duplicate@example.test', 'password' => 'password']);
        self::assertResponseStatusCodeSame(201);

        $client->jsonRequest('POST', '/api/register', ['email' => 'duplicate@example.test', 'password' => 'other']);
        self::assertResponseStatusCodeSame(409);
    }

    public function testLoginReturnsShortLivedJwtAndRefreshToken(): void
    {
        $client = static::createClient([], ['REMOTE_ADDR' => '10.9.' . random_int(0, 255) . '.' . random_int(1, 254)]);
        $client->jsonRequest('POST', '/api/register', ['email' => 'login@example.test', 'password' => 'password']);
        self::assertResponseStatusCodeSame(201);

        $client->jsonRequest('POST', '/api/login', ['email' => 'login@example.test', 'password' => 'password']);
        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('Bearer', $data['token_type']);
        self::assertSame(900, $data['expires_in']);
        self::assertNotEmpty($data['access_token']);
        self::assertNotEmpty($data['refresh_token']);

        $container = static::getContainer();
        $user = $container->get(\App\Repository\UserRepository::class)->findByEmail('login@example.test');
        self::assertNotNull($user);
        self::assertStringStartsWith('$argon2id$', $user->getPassword());
    }

    public function testWrongPasswordDoesNotAuthenticate(): void
    {
        $client = static::createClient([], ['REMOTE_ADDR' => '10.9.' . random_int(0, 255) . '.' . random_int(1, 254)]);
        $client->jsonRequest('POST', '/api/register', ['email' => 'wrong-password@example.test', 'password' => 'password']);
        $client->jsonRequest('POST', '/api/login', ['email' => 'wrong-password@example.test', 'password' => 'wrong']);

        self::assertResponseStatusCodeSame(401);
    }

    public function testRefreshTokenRotates(): void
    {
        $client = static::createClient([], ['REMOTE_ADDR' => '10.9.' . random_int(0, 255) . '.' . random_int(1, 254)]);
        $client->jsonRequest('POST', '/api/register', ['email' => 'refresh@example.test', 'password' => 'password']);
        $client->jsonRequest('POST', '/api/login', ['email' => 'refresh@example.test', 'password' => 'password']);
        $first = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $client->jsonRequest('POST', '/api/refresh', ['refresh_token' => $first['refresh_token']]);
        self::assertResponseIsSuccessful();
        $second = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertNotSame($first['refresh_token'], $second['refresh_token']);

        $client->jsonRequest('POST', '/api/refresh', ['refresh_token' => $first['refresh_token']]);
        self::assertResponseStatusCodeSame(401);
    }

    public function testAuthenticatedApiRequiresBearerToken(): void
    {
        $client = static::createClient([], ['REMOTE_ADDR' => '10.9.' . random_int(0, 255) . '.' . random_int(1, 254)]);
        $client->request('GET', '/api/me/wagers');
        self::assertResponseStatusCodeSame(401);
    }
}
