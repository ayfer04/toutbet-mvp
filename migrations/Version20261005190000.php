<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261005190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create users table for ToutBet authentication';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE users (id UUID NOT NULL, email VARCHAR(180) NOT NULL, password_hash VARCHAR(255) NOT NULL, roles JSON NOT NULL, refresh_token_hash VARCHAR(64) DEFAULT NULL, refresh_token_expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_USERS_EMAIL ON users (email)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_USERS_REFRESH_TOKEN_HASH ON users (refresh_token_hash) WHERE refresh_token_hash IS NOT NULL');
        $this->addSql("COMMENT ON COLUMN users.id IS '(DC2Type:guid)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE users');
    }
}
