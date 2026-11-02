<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'create-schema', description: 'Crea o actualiza las tablas de la base de datos')]
final class CreateSchemaCommand extends Command
{
    public function __construct(private readonly Connection $db)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $schema = $this->db->createSchemaManager();

        if (!$schema->tablesExist(['entry'])) {
            $this->db->executeStatement(<<<'SQL'
                CREATE TABLE entry (
                    id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                    title VARCHAR(255) NOT NULL,
                    content VARCHAR(5000) NOT NULL DEFAULT '',
                    created_at DATETIME NOT NULL
                )
                SQL);
        }

        // Columnas añadidas en la v2.1 (también actualiza bases de datos antiguas)
        $columns = array_map(fn ($c) => $c->getObjectName()->toString(), $schema->introspectTableColumnsByUnquotedName('entry'));
        if (!\in_array('user_id', $columns, true)) {
            $this->db->executeStatement('ALTER TABLE entry ADD COLUMN user_id INTEGER DEFAULT NULL');
        }
        if (!\in_array('category_id', $columns, true)) {
            $this->db->executeStatement('ALTER TABLE entry ADD COLUMN category_id INTEGER DEFAULT NULL');
        }
        if (!\in_array('status', $columns, true)) {
            // Las entradas ya existentes se consideran publicadas
            $this->db->executeStatement("ALTER TABLE entry ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'published'");
        }

        $this->db->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS user (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                username VARCHAR(100) NOT NULL UNIQUE,
                display_name VARCHAR(255) NOT NULL,
                password VARCHAR(255) NOT NULL,
                role VARCHAR(30) NOT NULL,
                created_at DATETIME NOT NULL
            )
            SQL);
        $this->db->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS category (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                name VARCHAR(100) NOT NULL,
                slug VARCHAR(100) NOT NULL UNIQUE
            )
            SQL);

        $this->db->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS page (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                title VARCHAR(255) NOT NULL,
                slug VARCHAR(150) NOT NULL UNIQUE,
                content TEXT NOT NULL DEFAULT '',
                in_menu INTEGER NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL
            )
            SQL);

        $output->writeln('<info>Esquema creado/actualizado.</info>');

        return Command::SUCCESS;
    }
}
