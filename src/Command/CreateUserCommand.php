<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:create-user',
    description: 'Crée un nouvel utilisateur'
)]
class CreateUserCommand extends Command
{
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
    }

    protected function configure(): void
    {
        $this
            ->addArgument('username', InputArgument::REQUIRED, 'Le nom d\'utilisateur')
            ->addArgument('password', InputArgument::REQUIRED, 'Le mot de passe')
            ->addArgument('roles', InputArgument::OPTIONAL, 'Les rôles (ROLE_USER, ROLE_ADMIN)', 'ROLE_USER');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $username = $input->getArgument('username');
        $password = $input->getArgument('password');
        $roles = [$input->getArgument('roles')];

        $user = new User();
        $user->setUsername($username);
        $user->setPassword(password_hash($password, PASSWORD_BCRYPT));
        $user->setRoles($roles);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $output->writeln('Utilisateur créé avec succès !');

        return Command::SUCCESS;
    }
}