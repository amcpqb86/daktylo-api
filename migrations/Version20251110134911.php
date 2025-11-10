<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251110134911 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE game_session ADD wiki_article_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE game_session ADD CONSTRAINT FK_4586AAFB4E9C5254 FOREIGN KEY (wiki_article_id) REFERENCES wiki_article (id)');
        $this->addSql('CREATE INDEX IDX_4586AAFB4E9C5254 ON game_session (wiki_article_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE game_session DROP FOREIGN KEY FK_4586AAFB4E9C5254');
        $this->addSql('DROP INDEX IDX_4586AAFB4E9C5254 ON game_session');
        $this->addSql('ALTER TABLE game_session DROP wiki_article_id');
    }
}
