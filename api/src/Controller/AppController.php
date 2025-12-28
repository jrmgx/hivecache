<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;

class AppController extends AbstractController
{
    /** @return array<mixed> */
    #[Route(path: '/', name: 'index', methods: ['GET'])]
    #[Template('base.html.twig')]
    public function index(): array
    {
        return [];
    }
}
