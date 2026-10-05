<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

final class JwtService
{
    private const ALGORITHM = 'HS256';
    private const TTL = 900;

    public function __construct(private readonly string $secret)
    {
        if (strlen($this->secret) < 32) {
            throw new \LogicException('APP_JWT_SECRET must contain at least 32 bytes.');
        }
    }

    public function issue(User $user): string
    {
        $now = time();
        $header = ['alg' => self::ALGORITHM, 'typ' => 'JWT'];
        $payload = [
            'sub' => $user->getId(),
            'iat' => $now,
            'exp' => $now + self::TTL,
        ];

        $encodedHeader = $this->base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR));
        $encodedPayload = $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));
        $signingInput = $encodedHeader . '.' . $encodedPayload;
        $signature = hash_hmac('sha256', $signingInput, $this->secret, true);

        return $signingInput . '.' . $this->base64UrlEncode($signature);
    }

    /** @return array{sub:string,iat:int,exp:int} */
    public function decodeAndVerify(string $jwt): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            throw new UnauthorizedHttpException('Bearer', 'Invalid token.');
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;
        try {
            $header = json_decode($this->base64UrlDecode($encodedHeader), true, 512, JSON_THROW_ON_ERROR);
            $payload = json_decode($this->base64UrlDecode($encodedPayload), true, 512, JSON_THROW_ON_ERROR);
            $signature = $this->base64UrlDecode($encodedSignature);
        } catch (\Throwable) {
            throw new UnauthorizedHttpException('Bearer', 'Invalid token.');
        }

        if (($header['alg'] ?? null) !== self::ALGORITHM || ($header['typ'] ?? null) !== 'JWT') {
            throw new UnauthorizedHttpException('Bearer', 'Invalid token.');
        }

        $expected = hash_hmac('sha256', $encodedHeader . '.' . $encodedPayload, $this->secret, true);
        if (!hash_equals($expected, $signature)) {
            throw new UnauthorizedHttpException('Bearer', 'Invalid token.');
        }

        $sub = $payload['sub'] ?? null;
        $exp = $payload['exp'] ?? null;
        $iat = $payload['iat'] ?? null;
        if (!is_string($sub) || !is_int($exp) || !is_int($iat) || $exp <= time() || $iat > time() + 30) {
            throw new UnauthorizedHttpException('Bearer', 'Invalid token.');
        }

        return ['sub' => $sub, 'iat' => $iat, 'exp' => $exp];
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        return base64_decode(strtr($value . str_repeat('=', (4 - strlen($value) % 4) % 4), '-_', '+/'), true) ?: throw new \RuntimeException('Invalid base64.');
    }
}
