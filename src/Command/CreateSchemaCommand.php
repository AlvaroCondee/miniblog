<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'create-schema', description: 'Executes the SQL needed to generate the database schema')]
final class CreateSchemaCommand extends Command
{
    public function __construct(private readonly Connection $db)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $schema = $this->db->createSchemaManager();

        if ($schema->tablesExist(['entry'])) {
            $output->writeln('<info>Schema already exists.</info>');

            return Command::SUCCESS;
        }

        $this->db->executeStatement(<<<'SQL'
            CREATE TABLE entry (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                title VARCHAR(255) NOT NULL,
                content VARCHAR(5000) NOT NULL DEFAULT '',
                created_at DATETIME NOT NULL
            )
            SQL);
        $output->writeln('<info>Done.</info>');

        return Command::SUCCESS;
    }
}
