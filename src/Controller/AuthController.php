<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\EmailVerificationService;
use App\Service\JwtService;
use App\Service\RefreshTokenService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class AuthController
{
    #[Route('/api/register', name: 'api_register', methods: ['POST'])]
    public function register(
        Request $request,
        ValidatorInterface $validator,
        UserRepository $users,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        RateLimiterFactory $registerLimiter,
        EmailVerificationService $emailVerification,
    ): JsonResponse {
        // STRIDE: Spoofing (faux comptes) / Denial of Service — 5 inscriptions par IP et par heure.
        if (!$registerLimiter->create($request->getClientIp() ?? 'unknown')->consume(1)->isAccepted()) {
            return new JsonResponse(['error' => 'Too many requests.'], JsonResponse::HTTP_TOO_MANY_REQUESTS);
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return new JsonResponse(['error' => 'Invalid JSON.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $email = is_string($data['email'] ?? null) ? trim($data['email']) : '';
        $password = is_string($data['password'] ?? null) ? $data['password'] : '';
        $violations = $validator->validate($email, [new Assert\NotBlank(), new Assert\Email(), new Assert\Length(max: 320)]);
        $passwordViolations = $validator->validate($password, [new Assert\NotBlank(), new Assert\Length(max: 4096)]);
        if (count($violations) > 0 || count($passwordViolations) > 0) {
            return new JsonResponse(['error' => 'Invalid credentials format.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        if ($users->findByEmail($email) !== null) {
            // STRIDE: Information Disclosure / Spoofing — generic account state must not be exposed.
            return new JsonResponse(['error' => 'Unable to create account.'], JsonResponse::HTTP_CONFLICT);
        }

        $user = new User($this->uuidV4(), $email, 'temporary');
        $userPasswordHash = $passwordHasher->hashPassword($user, $password);
        $user->setPasswordHash($userPasswordHash);

        // STRIDE: Elevation of Privilege — le client ne choisit jamais ses rôles librement :
        // seul un booléen est accepté, et il ne peut donner que ROLE_BOOKIE (jamais ROLE_ADMIN).
        if (($data['bookie'] ?? false) === true) {
            $user->setRoles(['ROLE_PARIEUR', 'ROLE_BOOKIE']);
        }

        $entityManager->persist($user);
        $entityManager->flush();
        $emailVerification->start($user);

        return new JsonResponse(['id' => $user->getId(), 'email' => $user->getEmail()], JsonResponse::HTTP_CREATED);
    }

    #[Route('/api/verify-email', name: 'api_verify_email', methods: ['POST'])]
    public function verifyEmail(Request $request, EmailVerificationService $emailVerification): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $token = is_array($data) && is_string($data['token'] ?? null) ? $data['token'] : '';

        // Jeton à usage unique, expirant après 24 h. Réponse identique pour tous les cas d'échec.
        if (!$emailVerification->verify($token)) {
            return new JsonResponse(['error' => 'Invalid or expired token.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        return new JsonResponse(['verified' => true]);
    }

    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function login(
        Request $request,
        UserRepository $users,
        UserPasswordHasherInterface $passwordHasher,
        JwtService $jwt,
        RefreshTokenService $refreshTokens,
        RateLimiterFactory $loginLimiter,
    ): JsonResponse {
        $limiter = $loginLimiter->create($request->getClientIp() ?? 'unknown');
        if (!$limiter->consume(1)->isAccepted()) {
            // STRIDE: Denial of Service — login attempts are rate-limited.
            return new JsonResponse(['error' => 'Too many requests.'], JsonResponse::HTTP_TOO_MANY_REQUESTS);
        }

        $data = json_decode($request->getContent(), true);
        $email = is_array($data) && is_string($data['email'] ?? null) ? trim($data['email']) : '';
        $password = is_array($data) && is_string($data['password'] ?? null) ? $data['password'] : '';
        $user = $users->findByEmail($email);

        if ($user === null || !$passwordHasher->isPasswordValid($user, $password)) {
            // STRIDE: Spoofing — reject invalid credentials without revealing which field failed.
            return new JsonResponse(['error' => 'Invalid credentials.'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $refreshToken = $refreshTokens->issue($user);
        return new JsonResponse([
            'access_token' => $jwt->issue($user),
            'token_type' => 'Bearer',
            'expires_in' => 900,
            'refresh_token' => $refreshToken,
        ]);
    }

    #[Route('/api/refresh', name: 'api_refresh', methods: ['POST'])]
    public function refresh(
        Request $request,
        UserRepository $users,
        JwtService $jwt,
        RefreshTokenService $refreshTokens,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $refreshToken = is_array($data) && is_string($data['refresh_token'] ?? null) ? $data['refresh_token'] : '';
        if ($refreshToken === '') {
            return new JsonResponse(['error' => 'Invalid refresh token.'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        // STRIDE: Spoofing — only the hash is queried; the raw refresh token is never persisted.
        $user = $users->findByRefreshTokenHash(hash('sha256', $refreshToken));
        if ($user !== null) {
            try {
                $newRefreshToken = $refreshTokens->rotate($user, $refreshToken);
                return new JsonResponse([
                    'access_token' => $jwt->issue($user),
                    'token_type' => 'Bearer',
                    'expires_in' => 900,
                    'refresh_token' => $newRefreshToken,
                ]);
            } catch (\RuntimeException) {
                // Fall through to the same generic response.
            }
        }

        return new JsonResponse(['error' => 'Invalid refresh token.'], JsonResponse::HTTP_UNAUTHORIZED);
    }

    private function uuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20));
    }
}
