<?php

declare(strict_types=1);

namespace App\Http;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class Json
{
    /** @return array<string, mixed>|null */
    public static function body(Request $request): ?array
    {
        try {
            $data = json_decode($request->getContent(), true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($data) ? $data : null;
    }

    public static function error(string $message, int $status): JsonResponse
    {
        // STRIDE: Information Disclosure — generic messages only, no stack trace or internal detail.
        return new JsonResponse(['error' => $message], $status);
    }

    /** @return array{0:int,1:int} page, perPage */
    public static function pagination(Request $request): array
    {
        // STRIDE: Denial of Service — mandatory pagination with a hard upper bound.
        $page = max(1, $request->query->getInt('page', 1));
        $perPage = min(50, max(1, $request->query->getInt('per_page', 20)));

        return [$page, $perPage];
    }
}
