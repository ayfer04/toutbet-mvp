<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\User;
use App\Service\JwtService;
use PHPUnit\Framework\TestCase;

final class JwtServiceTest extends TestCase
{
    public function testIssuedTokenCanBeVerified(): void
    {
        $service = new JwtService(str_repeat('a', 32));
        $user = new User('11111111-1111-4111-8111-111111111111', 'user@example.test', 'hash');
        $claims = $service->decodeAndVerify($service->issue($user));

        self::assertSame($user->getId(), $claims['sub']);
        self::assertGreaterThan(time(), $claims['exp']);
    }

    public function testTamperedTokenIsRejected(): void
    {
        $service = new JwtService(str_repeat('a', 32));
        $user = new User('11111111-1111-4111-8111-111111111111', 'user@example.test', 'hash');
        $token = $service->issue($user);
        $parts = explode('.', $token);
        $parts[1] = rtrim(strtr(base64_encode('{"sub":"attacker"}'), '+/', '-_'), '=');

        $this->expectException(\Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException::class);
        $service->decodeAndVerify(implode('.', $parts));
    }
}
