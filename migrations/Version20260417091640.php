<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260417091640 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE tournament ADD created_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE tournament ADD CONSTRAINT FK_BD5FB8D9B03A8386 FOREIGN KEY (created_by_id) REFERENCES "user" (id)');
        $this->addSql('CREATE INDEX IDX_BD5FB8D9B03A8386 ON tournament (created_by_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE tournament DROP CONSTRAINT FK_BD5FB8D9B03A8386');
        $this->addSql('DROP INDEX IDX_BD5FB8D9B03A8386');
        $this->addSql('ALTER TABLE tournament DROP created_by_id');
    }
}
