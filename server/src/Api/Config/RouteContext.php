<?php

namespace App\Api\Config;

use Symfony\Component\HttpFoundation\RequestStack;

final readonly class RouteContext
{
    public function __construct(
        private RequestStack $requestStack,
    ) {
    }

    public function getAction(): RouteAction
    {
        $parts = explode('_', $this->getRoute());
        $action = end($parts);
        if (!$action) {
            throw new \LogicException('Did not find the action associated to that route.');
        }

        return RouteAction::tryFrom($action)
            ?? throw new \LogicException("Can not find RouteAction::{$action}");
    }

    public function getType(): RouteType
    {
        $parts = explode('_', $this->getRoute());
        array_pop($parts);
        $type = implode('_', $parts) . '_';

        return RouteType::tryFrom($type)
            ?? throw new \LogicException("Can not find RouteType::{$type}");
    }

    private function getRoute(): string
    {
        return $this->requestStack->getMainRequest()?->attributes->get('_route')
            ?? throw new \LogicException('Did not find _route in request::attributes');
    }
}
