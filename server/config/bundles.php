<?php

$ALL_ENV_EXCEPT_PROD = ['dev' => true, 'test' => true, 'test_ap_server' => true];
$ALL_TEST_ENV = ['test' => true, 'test_ap_server' => true];

return [
    Symfony\Bundle\FrameworkBundle\FrameworkBundle::class => ['all' => true],
    Symfony\Bundle\TwigBundle\TwigBundle::class => ['all' => true],
    Symfony\Bundle\SecurityBundle\SecurityBundle::class => ['all' => true],
    Doctrine\Bundle\DoctrineBundle\DoctrineBundle::class => ['all' => true],
    Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle::class => ['all' => true],
    Symfony\Bundle\MakerBundle\MakerBundle::class => ['dev' => true],
    Doctrine\Bundle\FixturesBundle\DoctrineFixturesBundle::class => $ALL_ENV_EXCEPT_PROD,
    Zenstruck\Foundry\ZenstruckFoundryBundle::class => $ALL_ENV_EXCEPT_PROD,
    DAMA\DoctrineTestBundle\DAMADoctrineTestBundle::class => $ALL_TEST_ENV,
    Nelmio\Alice\Bridge\Symfony\NelmioAliceBundle::class => $ALL_ENV_EXCEPT_PROD,
    Fidry\AliceDataFixtures\Bridge\Symfony\FidryAliceDataFixturesBundle::class => $ALL_ENV_EXCEPT_PROD,
    Hautelook\AliceBundle\HautelookAliceBundle::class => $ALL_ENV_EXCEPT_PROD,
    League\FlysystemBundle\FlysystemBundle::class => ['all' => true],
    Twig\Extra\TwigExtraBundle\TwigExtraBundle::class => ['all' => true],
    Symfony\Bundle\WebProfilerBundle\WebProfilerBundle::class => $ALL_ENV_EXCEPT_PROD,
    Symfony\Bundle\MonologBundle\MonologBundle::class => ['all' => true],
    Symfony\Bundle\DebugBundle\DebugBundle::class => $ALL_ENV_EXCEPT_PROD,
    Lexik\Bundle\JWTAuthenticationBundle\LexikJWTAuthenticationBundle::class => ['all' => true],
    Symfony\UX\TwigComponent\TwigComponentBundle::class => ['all' => true],
    EasyCorp\Bundle\EasyAdminBundle\EasyAdminBundle::class => ['all' => true],
    SymfonyCasts\Bundle\ResetPassword\SymfonyCastsResetPasswordBundle::class => ['all' => true],
    Jrmgx\InteractiveBundle\InteractiveBundle::class => ['dev' => true],
];
