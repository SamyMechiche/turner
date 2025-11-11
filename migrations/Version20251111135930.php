<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251111135930 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        // Add is_verified column (defaults to 1/true for all users - verified)
        $this->addSql('ALTER TABLE user ADD is_verified TINYINT(1) NOT NULL DEFAULT 1');
        // Add fname and lname columns
        $this->addSql('ALTER TABLE user ADD fname VARCHAR(100) DEFAULT NULL, ADD lname VARCHAR(100) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user DROP is_verified, DROP fname, DROP lname');
    }
}
