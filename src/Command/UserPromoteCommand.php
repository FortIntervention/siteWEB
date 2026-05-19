<?php

namespace App\Command;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'user:promote', description: 'Ajoute un rôle à un utilisateur')]
class UserPromoteCommand extends Command
{
    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('emails', InputArgument::REQUIRED, 'L\'emails de l\'utilisateur')
            ->addArgument('role', InputArgument::REQUIRED, 'Le rôle à ajouter (ex: ROLE_ADMIN)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = $input->getArgument('emails');
        $role = strtoupper($input->getArgument('role'));

        $user = $this->userRepository->findOneBy(['emails' => $email]);

        if (!$user) {
            $io->error(sprintf('Utilisateur avec l\'emails "%s" non trouvé.', $email));
            return Command::FAILURE;
        }

        $roles = $user->getRoles();
        if (!in_array($role, $roles)) {
            $roles[] = $role;
            $user->setRoles($roles);
            $this->entityManager->flush();
            $io->success(sprintf('Le rôle %s a été ajouté à l\'utilisateur %s.', $role, $email));
        } else {
            $io->note(sprintf('L\'utilisateur %s possède déjà le rôle %s.', $email, $role));
        }

        return Command::SUCCESS;
    }
}
