<?php

namespace App\Tests;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class BaseAdminTestCase extends WebTestCase
{
    protected KernelBrowser $client;
    protected object $container;
    protected EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = self::createClient();
        $this->container = self::getContainer();
        $this->entityManager = $this->container->get('doctrine')->getManager();
    }
}
