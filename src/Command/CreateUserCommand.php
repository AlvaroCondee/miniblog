<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\UserRepository;
use App\Security\User;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

#[AsCommand(name: 'app:create-user', description: 'Crea un usuario (por ejemplo, el primer administrador)')]
final class CreateUserCommand extends Command
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordHasherFactoryInterface $hashers,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('username', InputArgument::REQUIRED, 'Nombre de usuario')
            ->addArgument('name', InputArgument::REQUIRED, 'Nombre para mostrar')
            ->addOption('role', null, InputOption::VALUE_REQUIRED, 'ROLE_ADMIN, ROLE_TEACHER o ROLE_STUDENT', User::ROLE_ADMIN);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $role = (string) $input->getOption('role');

        if (!\in_array($role, User::ROLE_LABELS, true)) {
            $io->error('Rol no válido.');

            return Command::FAILURE;
        }
        if (null !== $this->users->findByUsername($input->getArgument('username'))) {
            $io->error('Ya existe ese usuario.');

            return Command::FAILURE;
        }

        $password = $io->askHidden('Contraseña (mínimo 8 caracteres)');
        if (!\is_string($password) || \strlen($password) < 8) {
            $io->error('La contraseña es demasiado corta.');

            return Command::FAILURE;
        }

        $hash = $this->hashers->getPasswordHasher(User::class)->hash($password);
        $this->users->insert($input->getArgument('username'), $input->getArgument('name'), $hash, $role);
        $io->success('Usuario creado.');

        return Command::SUCCESS;
    }
}
