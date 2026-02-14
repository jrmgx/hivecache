<?php

namespace App\Tests\Admin\Command;

use App\Entity\Admin;
use App\Repository\AdminRepository;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class AdminCreateCommandTest extends KernelTestCase
{
    public function testExecute(): void
    {
        self::bootKernel();
        $application = new Application(self::$kernel);

        $command = $application->find('hivecache:admin:create');
        $commandTester = new CommandTester($command);
        $commandTester->setInputs(['admin@test.com']);
        $commandTester->execute([]);

        $commandTester->assertCommandIsSuccessful();

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Password:', $output);
        $this->assertMatchesRegularExpression('/Password:.*[a-f0-9]{24}/', $output);

        /** @var AdminRepository $adminRepository */
        $adminRepository = self::getContainer()->get(AdminRepository::class);
        $admin = $adminRepository->findOneBy(['email' => 'admin@test.com']);
        $this->assertInstanceOf(Admin::class, $admin);
        $this->assertContains('ROLE_ADMIN', $admin->getRoles());
    }
}
