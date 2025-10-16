<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251016190655 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__subscription AS SELECT id, user_id, stripe_subscription_id, status, current_period_end FROM subscription');
        $this->addSql('DROP TABLE subscription');
        $this->addSql('CREATE TABLE subscription (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user_id INTEGER NOT NULL, stripe_subscription_id VARCHAR(64) NOT NULL, status VARCHAR(32) NOT NULL, current_period_end DATETIME DEFAULT NULL --(DC2Type:datetime_immutable)
        , created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , updated_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , CONSTRAINT FK_A3C664D3A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO subscription (id, user_id, stripe_subscription_id, status, current_period_end) SELECT id, user_id, stripe_subscription_id, status, current_period_end FROM __temp__subscription');
        $this->addSql('DROP TABLE __temp__subscription');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_A3C664D3B5DBB761 ON subscription (stripe_subscription_id)');
        $this->addSql('CREATE INDEX idx_subscription_status ON subscription (status)');
        $this->addSql('CREATE INDEX idx_subscription_period_end ON subscription (current_period_end)');
        $this->addSql('CREATE INDEX idx_subscription_user ON subscription (user_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__subscription AS SELECT id, user_id, stripe_subscription_id, status, current_period_end FROM subscription');
        $this->addSql('DROP TABLE subscription');
        $this->addSql('CREATE TABLE subscription (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user_id INTEGER NOT NULL, stripe_subscription_id VARCHAR(64) NOT NULL, status VARCHAR(32) NOT NULL, current_period_end DATETIME DEFAULT NULL --(DC2Type:datetime_immutable)
        , CONSTRAINT FK_A3C664D3A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO subscription (id, user_id, stripe_subscription_id, status, current_period_end) SELECT id, user_id, stripe_subscription_id, status, current_period_end FROM __temp__subscription');
        $this->addSql('DROP TABLE __temp__subscription');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_A3C664D3B5DBB761 ON subscription (stripe_subscription_id)');
        $this->addSql('CREATE INDEX IDX_A3C664D3A76ED395 ON subscription (user_id)');
    }
}
