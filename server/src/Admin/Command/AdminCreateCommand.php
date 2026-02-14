<?php

namespace App\Admin\Command;

use App\Entity\Admin;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'hivecache:admin:create',
    description: 'Add an admin user to your instance',
)]
class AdminCreateCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        while (empty($email = $io->askQuestion(new Question('Admin email')))) {
            $io->error('Email is required.');
        }

        $admin = new Admin();
        $admin->email = $email;
        $admin->setRoles(['ROLE_ADMIN']);

        $plainPassword = bin2hex(random_bytes(12));
        $admin->setPassword($this->passwordHasher->hashPassword($admin, $plainPassword));

        $this->entityManager->persist($admin);
        $this->entityManager->flush();

        $io->writeln(\sprintf('Password: <info>%s</info>', $plainPassword));
        $io->warning("Please change this password!\nGot to /admin/reset-password and follow the instructions.");

        $io->success('Admin created.');

        return Command::SUCCESS;
    }
}
