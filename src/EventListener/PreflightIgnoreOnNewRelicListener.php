<?php

namespace App\EventListener;

use Symfony\Component\HttpKernel\Event\ResponseEvent;

class PreflightIgnoreOnNewRelicListener
{
    public function onKernelResponse(ResponseEvent $event)
    {
        if ('OPTIONS' === $event->getRequest()->getMethod()) {
            // Handle the OPTIONS request as needed
            // For example, set appropriate CORS headers
            $response = $event->getResponse();
            $response->headers->set('Access-Control-Allow-Origin', '*');
            $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
            $response->headers->set('Access-Control-Allow-Headers', 'Content-Type');
        }
    }
}
