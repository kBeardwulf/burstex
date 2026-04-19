<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260419133616 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE matches ADD next_match_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE matches ADD CONSTRAINT FK_62615BA12A4E038 FOREIGN KEY (next_match_id) REFERENCES matches (id)');
        $this->addSql('CREATE INDEX IDX_62615BA12A4E038 ON matches (next_match_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE matches DROP CONSTRAINT FK_62615BA12A4E038');
        $this->addSql('DROP INDEX IDX_62615BA12A4E038');
        $this->addSql('ALTER TABLE matches DROP next_match_id');
    }
}
