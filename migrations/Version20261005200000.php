<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261005200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Email verification, bets, invitations, wagers, ledger, append-only audit log';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD email_verified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, ADD email_verification_token_hash VARCHAR(64) DEFAULT NULL, ADD email_verification_expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_users_email_verification ON users (email_verification_token_hash) WHERE email_verification_token_hash IS NOT NULL');

        $this->addSql('CREATE TABLE bets (id UUID NOT NULL, bookie_id UUID NOT NULL, title VARCHAR(140) NOT NULL, min_stake_cents INT NOT NULL, max_stake_cents INT NOT NULL, odds_hundredths INT NOT NULL, bookie_commission_bps INT NOT NULL, status VARCHAR(16) NOT NULL, closes_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, outcome BOOLEAN DEFAULT NULL, result_validated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, wager_count INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_bets_bookie ON bets (bookie_id)');
        $this->addSql('ALTER TABLE bets ADD CONSTRAINT fk_bets_bookie FOREIGN KEY (bookie_id) REFERENCES users (id)');
        // Garde-fous en base, en plus des contrôles applicatifs (défense en profondeur).
        $this->addSql("ALTER TABLE bets ADD CONSTRAINT chk_bets_stakes CHECK (min_stake_cents > 0 AND max_stake_cents >= min_stake_cents)");
        $this->addSql("ALTER TABLE bets ADD CONSTRAINT chk_bets_commission CHECK (bookie_commission_bps BETWEEN 0 AND 1000)");
        $this->addSql("ALTER TABLE bets ADD CONSTRAINT chk_bets_status CHECK (status IN ('open', 'closed', 'settled'))");

        $this->addSql('CREATE TABLE invitations (id UUID NOT NULL, bet_id UUID NOT NULL, invitee_id UUID NOT NULL, revoked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_invitation_bet_invitee ON invitations (bet_id, invitee_id)');
        $this->addSql('ALTER TABLE invitations ADD CONSTRAINT fk_invitations_bet FOREIGN KEY (bet_id) REFERENCES bets (id)');
        $this->addSql('ALTER TABLE invitations ADD CONSTRAINT fk_invitations_invitee FOREIGN KEY (invitee_id) REFERENCES users (id)');

        $this->addSql('CREATE TABLE wagers (id UUID NOT NULL, bet_id UUID NOT NULL, bettor_id UUID NOT NULL, prediction BOOLEAN NOT NULL, stake_cents INT NOT NULL, odds_snapshot_hundredths INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_wager_bet_bettor ON wagers (bet_id, bettor_id)');
        $this->addSql('CREATE INDEX idx_wagers_bettor ON wagers (bettor_id)');
        $this->addSql('ALTER TABLE wagers ADD CONSTRAINT fk_wagers_bet FOREIGN KEY (bet_id) REFERENCES bets (id)');
        $this->addSql('ALTER TABLE wagers ADD CONSTRAINT fk_wagers_bettor FOREIGN KEY (bettor_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE wagers ADD CONSTRAINT chk_wagers_stake CHECK (stake_cents > 0)');

        $this->addSql('CREATE TABLE ledger_transactions (id UUID NOT NULL, user_id UUID DEFAULT NULL, bet_id UUID DEFAULT NULL, type VARCHAR(32) NOT NULL, amount_cents BIGINT NOT NULL, idempotency_key VARCHAR(128) NOT NULL, external_reference VARCHAR(128) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_ledger_idempotency_key ON ledger_transactions (idempotency_key)');
        $this->addSql('CREATE INDEX idx_ledger_user ON ledger_transactions (user_id)');
        $this->addSql('ALTER TABLE ledger_transactions ADD CONSTRAINT fk_ledger_user FOREIGN KEY (user_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE ledger_transactions ADD CONSTRAINT fk_ledger_bet FOREIGN KEY (bet_id) REFERENCES bets (id)');

        $this->addSql('CREATE TABLE audit_logs (sequence SERIAL NOT NULL, actor_id UUID DEFAULT NULL, action VARCHAR(64) NOT NULL, payload JSON NOT NULL, previous_hash VARCHAR(64) NOT NULL, hash VARCHAR(64) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(sequence))');
        $this->addSql('CREATE UNIQUE INDEX uniq_audit_logs_hash ON audit_logs (hash)');

        // STRIDE: Repudiation / Tampering — le grand livre et le journal d'audit sont append-only, imposé par la base.
        $this->addSql("CREATE FUNCTION forbid_mutation() RETURNS trigger AS \$\$ BEGIN RAISE EXCEPTION 'Table % is append-only', TG_TABLE_NAME; END; \$\$ LANGUAGE plpgsql");
        $this->addSql('CREATE TRIGGER audit_logs_append_only BEFORE UPDATE OR DELETE ON audit_logs FOR EACH ROW EXECUTE FUNCTION forbid_mutation()');
        $this->addSql('CREATE TRIGGER ledger_append_only BEFORE UPDATE OR DELETE ON ledger_transactions FOR EACH ROW EXECUTE FUNCTION forbid_mutation()');
        $this->addSql('CREATE TRIGGER wagers_append_only BEFORE UPDATE OR DELETE ON wagers FOR EACH ROW EXECUTE FUNCTION forbid_mutation()');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE audit_logs');
        $this->addSql('DROP TABLE ledger_transactions');
        $this->addSql('DROP TABLE wagers');
        $this->addSql('DROP TABLE invitations');
        $this->addSql('DROP TABLE bets');
        $this->addSql('DROP FUNCTION forbid_mutation()');
        $this->addSql('ALTER TABLE users DROP email_verified_at, DROP email_verification_token_hash, DROP email_verification_expires_at');
    }
}
