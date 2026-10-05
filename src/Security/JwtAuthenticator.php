<?php

declare(strict_types=1);

namespace App\Security;

use App\Repository\UserRepository;
use App\Service\JwtService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

final class JwtAuthenticator extends AbstractAuthenticator
{
    public function __construct(private readonly JwtService $jwt, private readonly UserRepository $users) {}

    public function supports(Request $request): ?bool
    {
        return str_starts_with((string) $request->headers->get('Authorization'), 'Bearer ');
    }

    public function authenticate(Request $request): Passport
    {
        $authorization = (string) $request->headers->get('Authorization');
        $jwt = trim(substr($authorization, 7));
        $claims = $this->jwt->decodeAndVerify($jwt);

        return new SelfValidatingPassport(new UserBadge($claims['sub'], function (string $id) {
            $user = $this->users->find($id);
            if ($user === null) {
                throw new AuthenticationException('User not found.');
            }
            return $user;
        }));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse(['error' => 'Authentication required.'], Response::HTTP_UNAUTHORIZED);
    }
}
