<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration pour mettre à jour les rôles dans les tables role et user.
 */
final class Version20250102151534 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Met à jour les rôles dans les tables role et user pour utiliser le préfixe ROLE_';
    }

    public function up(Schema $schema): void
    {
        // Met à jour les rôles dans la table Role
        $this->addSql('UPDATE role SET name = CONCAT(\'ROLE_\', name) WHERE name NOT LIKE \'ROLE_%\'');

        // Mise à jour des rôles dans la table User (Assure-toi que ce code est adapté à ta structure)
        $this->addSql('UPDATE "user" SET roles = \'["ROLE_MANAGER"]\' WHERE roles::text LIKE \'%"Manager"%\'');
        $this->addSql('UPDATE "user" SET roles = \'["ROLE_DEV"]\' WHERE roles::text LIKE \'%"Dev"%\'');

        // Convertir en JSONB après les mises à jour des rôles
        $this->addSql('ALTER TABLE "user" ALTER COLUMN roles TYPE jsonb USING roles::jsonb');
    }

    public function down(Schema $schema): void
    {
        // Convertir en JSON avant les modifications
        $this->addSql('ALTER TABLE "user" ALTER COLUMN roles TYPE json USING roles::json');

        // Revertir les changements des rôles
        $this->addSql('UPDATE role SET name = REPLACE(name, \'ROLE_\', \'\') WHERE name LIKE \'ROLE_%\'');

        $this->addSql('UPDATE "user" SET roles = \'["Manager"]\' WHERE roles::text LIKE \'%"ROLE_MANAGER"%\'');
        $this->addSql('UPDATE "user" SET roles = \'["Dev"]\' WHERE roles::text LIKE \'%"ROLE_DEV"%\'');
    }
}
