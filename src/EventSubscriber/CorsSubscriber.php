<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * CORS limité à l'origine du front (FRONTEND_ORIGIN), jamais "*".
 */
final class CorsSubscriber implements EventSubscriberInterface
{
    public function __construct(#[Autowire('%env(FRONTEND_ORIGIN)%')] private readonly string $allowedOrigin) {}

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onRequest', 250], KernelEvents::RESPONSE => 'onResponse'];
    }

    public function onRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if ($request->isMethod('OPTIONS') && $request->headers->has('Access-Control-Request-Method')) {
            $event->setResponse(new Response('', $request->headers->get('Origin') === $this->allowedOrigin ? 204 : 403));
        }
    }

    public function onResponse(ResponseEvent $event): void
    {
        if ($event->getRequest()->headers->get('Origin') !== $this->allowedOrigin) {
            return;
        }
        $headers = $event->getResponse()->headers;
        $headers->set('Access-Control-Allow-Origin', $this->allowedOrigin);
        $headers->set('Access-Control-Allow-Methods', 'GET, POST, PATCH, DELETE');
        $headers->set('Access-Control-Allow-Headers', 'Authorization, Content-Type');
        $headers->set('Vary', 'Origin');
    }
}
