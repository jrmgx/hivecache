<?php

namespace App\Api;

use App\Api\Config\RouteAction;
use App\Api\Config\RouteType;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class UrlGenerator
{
    public function __construct(
        #[Autowire('%instanceHost%')]
        private string $instanceHost,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * @param array<string, string> $params
     */
    public function generate(RouteType $type, RouteAction $action, array $params = []): string
    {
        return 'https://' .
            $this->instanceHost .
            $this->urlGenerator->generate($type->value . $action->value, $params);
    }
}
